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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Types\Type;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use midgard_connection;

class connection
{
    /**
     * @var Monolog\Logger
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
    private static $loglevels = array
    (
        'error' => Logger::ERROR,
        'warn' => Logger::WARNING,
        'warning' => Logger::WARNING,
        'info' => Logger::NOTICE,
        'message' => Logger::INFO,
        'debug' => Logger::DEBUG
    );

    private $user;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \midgard\portable\storage\connection
     */
    protected static $instance;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @return \Doctrine\ORM\EntityManagerInterface
     */
    public static function get_em()
    {
        if (self::$instance === null)
        {
            throw new \Exception('Not initialized');
        }
        return self::$instance->em;
    }

    public static function get_user()
    {
        return self::$instance->user;
    }

    public static function set_user(user $user = null)
    {
        self::$instance->user = $user;
    }

    public static function generate_guid()
    {
        $sql = 'SELECT ' . self::get_em()->getConnection()->getDatabasePlatform()->getGuidExpression();
        return md5(self::get_em()->getConnection()->query($sql)->fetchColumn(0));
    }

    public static function initialize(driver $driver, array $db_config, $dev_mode = true)
    {
        $vardir = $driver->get_vardir();

        // generate and include midgard_objects.php if its a fresh namespace
        // otherwhise it should be included already
        if ($driver->is_fresh_namespace())
        {
            if (   $dev_mode
                || !file_exists($vardir . '/midgard_objects.php'))
            {
                $classgenerator = new classgenerator($driver->get_manager(), $vardir . '/midgard_objects.php', $dev_mode);
                $classgenerator->write($driver->get_namespace());
            }
            include $vardir . '/midgard_objects.php';
        }

        $config = \Doctrine\ORM\Tools\Setup::createConfiguration($dev_mode);
        if (!$dev_mode)
        {
            $config->setProxyDir($vardir . '/cache');
            $config->setAutoGenerateProxyClasses(!$dev_mode);
        }
        $config->addFilter('softdelete', 'midgard\\portable\\storage\\filter\\softdelete');

        $config->setMetadataDriverImpl($driver);
        $config->addEntityNamespace('midgard', $driver->get_namespace());
        $config->setClassMetadataFactoryName('\\midgard\\portable\\mapping\\factory');

        $em = \Doctrine\ORM\EntityManager::create($db_config, $config);
        $em->getFilters()->enable('softdelete');
        $em->getEventManager()->addEventSubscriber(new subscriber);

        if (!Type::hasType(datetime::TYPE))
        {
            Type::addType(datetime::TYPE, 'midgard\portable\storage\type\datetime');
        }

        self::$instance = new static($em);

        $mgd_config = new config;
        $mgd_config->vardir = $vardir;
        $mgd_config->cachedir = $vardir . '/cache';
        $mgd_config->blobdir = $vardir . '/blobs';
        $mgd_config->sharedir = $vardir . '/schemas';
        $mgd_config->logfilename = $vardir . '/log/midgard-portable.log';
        // TODO: Set rest of config values from $config and $driver

        $midgard = midgard_connection::get_instance();
        $midgard->open_config($mgd_config);
        $level = self::$loglevels[$midgard->get_loglevel()];
        if ($level === Logger::DEBUG)
        {
            $logger = new Logger('doctrine');
            $logger->pushHandler(new StreamHandler($midgard->config->logfilename, $level));

            self::get_em()->getConnection()->getConfiguration()->setSQLLogger(new sqllogger($logger));
        }
    }

    /**
     * Get Logger instance
     *
     * @return Monolog\Logger
     */
    public static function log()
    {
        if (self::$logger === null)
        {
            $midgard = midgard_connection::get_instance();
            if ($midgard->config->logfilename)
            {
                $logdir = dirname($midgard->config->logfilename);
                if (   !is_dir($logdir)
                    && !mkdir($logdir, 0777, true))
                {
                    throw exception::user_data('Log directory could not be created');
                }
                self::$logger = new Logger('midgard-portable');
                self::$logger->pushHandler(new StreamHandler($midgard->config->logfilename, self::$loglevels[$midgard->get_loglevel()]));
            }
            else
            {
                throw exception::user_data('log filename not set in config');
            }
        }
        return self::$logger;
    }
}