<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage;

use midgard\portable\driver;
use midgard\portable\classgenerator;
use midgard\portable\api\user;
use midgard\portable\api\config;
use midgard\portable\api\error\exception;
use midgard\portable\storage\type\datetime;
use Doctrine\ORM\EntityManager;
use Doctrine\DBAL\Types\Type;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use midgard_connection;
use midgard\portable\storage\filter\softdelete;
use midgard\portable\mapping\factory;
use Doctrine\DBAL\Types\Types;

class connection
{
    /**
     * @var Logger
     */
    private static $logger;

    /**
     * Loglevel translation table.
     *
     * The semantics of info and notice/message are unfortunately reversed between Monolog
     * and Midgard, so it looks a bit confusing..
     *
     * @var array
     */
    private static $loglevels = [
        'error' => Logger::ERROR,
        'warn' => Logger::WARNING,
        'warning' => Logger::WARNING,
        'info' => Logger::NOTICE,
        'message' => Logger::INFO,
        'debug' => Logger::DEBUG
    ];

    /**
     * Flag for automatically starting up during initialize
     *
     * @var boolean
     */
    private static $autostart = true;

    /**
     * Initialization parameters
     *
     * @param array
     */
    private static $parameters = [];

    private $user;

    private $namespace;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var \midgard\portable\storage\connection
     */
    protected static $instance;

    public function __construct(EntityManager $em, string $namespace)
    {
        $this->em = $em;
        $this->namespace = $namespace;
    }

    public static function get_em() : EntityManager
    {
        if (self::$instance === null) {
            throw new \Exception('Not initialized');
        }
        return self::$instance->em;
    }

    public static function get_user() : ?user
    {
        return self::$instance->user;
    }

    public static function set_user(user $user = null)
    {
        if (self::$instance === null) {
            throw new \Exception('Not initialized');
        }
        self::$instance->user = $user;
    }

    public static function get_fqcn(string $classname) : string
    {
        if (self::$instance->namespace) {
            return self::$instance->namespace . '\\' . $classname;
        }
        return $classname;
    }

    /**
     * Generate a new GUID
     */
    public static function generate_guid() : string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Invalidate ClassMetadata cache
     */
    public static function invalidate_cache()
    {
        if ($cache = self::get_em()->getConfiguration()->getMetadataCache()) {
            $cache->clear();
        }
    }

    /**
     * Toggle autostart
     */
    public static function set_autostart(bool $autostart)
    {
        self::$autostart = $autostart;
    }

    /**
     * Initialize Midgard connection
     */
    public static function initialize(driver $driver, array $db_config, bool $dev_mode = false)
    {
        $vardir = $driver->get_vardir();

        $mgd_config = new config;
        $mgd_config->vardir = $vardir;
        $mgd_config->cachedir = $vardir . '/cache';
        $mgd_config->blobdir = $vardir . '/blobs';
        $mgd_config->sharedir = $vardir . '/schemas';
        $mgd_config->logfilename = $vardir . '/log/midgard-portable.log';
        // TODO: Set rest of config values from $config and $driver

        // we open the config before startup to have logfile available
        midgard_connection::get_instance()->open_config($mgd_config);

        self::$parameters = ['driver' => $driver, 'db_config' => $db_config, 'dev_mode' => $dev_mode];
        if (self::$autostart) {
            static::startup();
        }
    }

    public static function get_parameter(string $name)
    {
        if (!array_key_exists($name, self::$parameters)) {
            throw new \RuntimeException('Parameter "' . $name . '" is not available');
        }
        return self::$parameters[$name];
    }

    /**
     * Start the API emulation layer
     */
    public static function startup()
    {
        if (empty(self::$parameters)) {
            throw new \RuntimeException('Not initialized');
        }
        $driver = self::$parameters['driver'];
        $db_config = self::$parameters['db_config'];
        $dev_mode = self::$parameters['dev_mode'];
        $vardir = $driver->get_vardir();
        // generate and include entities file if its a fresh namespace
        // otherwise it should be included already
        if ($driver->is_fresh_namespace()) {
            $entityfile = $vardir . '/mgdschema_classes.php';
            if ($dev_mode || !file_exists($entityfile)) {
                $classgenerator = new classgenerator($driver->get_manager(), $entityfile, $dev_mode);
                $classgenerator->write($driver->get_namespace());
            }
            require $entityfile;
        }

        $config = \Doctrine\ORM\Tools\Setup::createConfiguration($dev_mode, $vardir . '/cache');
        $config->addFilter('softdelete', softdelete::class);
        $config->setMetadataDriverImpl($driver);
        $config->setClassMetadataFactoryName(factory::class);

        if (!array_key_exists('charset', $db_config)) {
            $db_config['charset'] = 'utf8';
        }

        $em = \Doctrine\ORM\EntityManager::create($db_config, $config);
        $em->getFilters()->enable('softdelete');
        $em->getEventManager()->addEventSubscriber(new subscriber);

        Type::overrideType(Types::DATETIME_MUTABLE, datetime::class);

        $midgard = midgard_connection::get_instance();
        $level = self::$loglevels[$midgard->get_loglevel()];
        if ($level === Logger::DEBUG) {
            $logger = new Logger('doctrine');
            $logger->pushHandler(new StreamHandler($midgard->config->logfilename, $level));

            $em->getConnection()->getConfiguration()->setSQLLogger(new sqllogger($logger));
        }

        self::$instance = new static($em, $driver->get_namespace());
    }

    /**
     * Get Logger instance
     */
    public static function log() : Logger
    {
        if (self::$logger === null) {
            $midgard = midgard_connection::get_instance();
            if ($midgard->config->logfilename) {
                $logdir = dirname($midgard->config->logfilename);
                if (   !is_dir($logdir)
                    && !mkdir($logdir, 0777, true)) {
                    throw exception::user_data('Log directory could not be created');
                }
                self::$logger = new Logger('midgard-portable');
                self::$logger->pushHandler(new StreamHandler($midgard->config->logfilename, self::$loglevels[$midgard->get_loglevel()]));
            } else {
                throw exception::user_data('log filename not set in config');
            }
        }
        return self::$logger;
    }
}
