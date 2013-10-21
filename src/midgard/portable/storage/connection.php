<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage;

use midgard\portable\driver;
use midgard\portable\api\user;
use midgard\portable\storage\type\datetime;
use midgard\portable\storage\subscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Types\Type;

class connection
{
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

    public static function initialize(driver $driver, array $db_config)
    {
        $config = \Doctrine\ORM\Tools\Setup::createConfiguration(true);
        $config->addFilter('softdelete', 'midgard\\portable\\storage\\filter\\softdelete');

        //triggers initialize()
        $driver->getAllClassNames();
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
    }
}