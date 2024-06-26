<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Proxy\ProxyGenerator;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Mapping\MappingException;
use midgard\portable\storage\connection;
use Doctrine\ORM\EntityManager;
use midgard\portable\command\schema;

class midgard_storage
{
    public static function create_base_storage() : bool
    {
        $em = connection::get_em();

        if (!self::create_class_storage(connection::get_fqcn('midgard_repligard'))) {
            return false;
        }
        if (!self::create_class_storage(connection::get_fqcn('midgard_person'))) {
            return false;
        }
        if (!self::create_class_storage(connection::get_fqcn('midgard_user'))) {
            return false;
        }

        $admin = $em->find(connection::get_fqcn('midgard_user'), 1);

        if ($admin === null) {
            $fqcn = connection::get_fqcn('midgard_person');
            $person = new $fqcn;
            $person->firstname = 'Midgard';
            $person->lastname = 'Administrator';
            $person->create();

            $fqcn = connection::get_fqcn('midgard_user');
            $admin = new $fqcn;
            $admin->authtype = 'Legacy';
            $admin->authtypeid = 2;
            $admin->login = 'admin';
            $admin->password = password_hash('password', PASSWORD_DEFAULT);
            $admin->active = true;
            $admin->usertype = 2;
            $admin->set_person($person);
            $admin->create();
        }

        return true;
    }

    public static function create_class_storage(string $classname) : bool
    {
        $em = connection::get_em();

        $cm = self::get_cm($em, $classname);
        if ($cm === null) {
            return false;
        }

        if (!$em->getConnection()->createSchemaManager()->tablesExist([$cm->getTableName()])) {
            $tool = new SchemaTool($em);
            $tool->createSchema([$cm]);
        }

        self::generate_proxyfile($cm);

        return true;
    }

    private static function generate_proxyfile(ClassMetadata $cm)
    {
        $em = connection::get_em();
        $generator = new ProxyGenerator($em->getConfiguration()->getProxyDir(), $em->getConfiguration()->getProxyNamespace());
        $generator->setPlaceholder('baseProxyInterface', 'Doctrine\ORM\Proxy\Proxy');
        $filename = $generator->getProxyFileName($cm->getName());
        if (file_exists($filename)) {
            unlink($filename);
        }
        $generator->generateProxyClass($cm, $filename);
    }

    private static function get_cm(EntityManager $em, string $classname) : ?\Doctrine\ORM\Mapping\ClassMetadata
    {
        if (!class_exists($classname)) {
            // if the class doesn't exist (e.g. for some_random_string), there is really nothing we could do
            return null;
        }

        $factory = $em->getMetadataFactory();
        try {
            return $factory->getMetadataFor($classname);
        } catch (MappingException) {
            // add namespace
            $classname = connection::get_fqcn($classname);
            try {
                return $factory->getMetadataFor($classname);
            } catch (MappingException) {
                // check for merged classes (duplicate tablenames)
                $classname = get_class(new $classname);
                return $factory->getMetadataFor($classname);
            }
        }
    }

    /**
     * Update DB table according to MgdSchema information.
     *
     * this does not use SchemaTool's updateSchema, since this would delete columns that are no longer
     * in the MgdSchema definition
     */
    public static function update_class_storage(string $classname) : bool
    {
        $em = connection::get_em();
        $cm = self::get_cm($em, $classname);
        if ($cm === null) {
            return false;
        }
        $sm = $em->getConnection()->createSchemaManager();
        if ($sm->tablesExist([$cm->getTableName()])) {
            $tool = new SchemaTool($em);
            $conn = $em->getConnection();
            $from = $sm->introspectSchema();
            $to = $tool->getSchemaFromMetadata([$cm]);
            $diff = schema::diff($from, $to, false);
            $sql = $conn->getDatabasePlatform()->getAlterSchemaSQL($diff);

            foreach ($sql as $sql_line) {
                $conn->executeQuery($sql_line);
            }

            self::generate_proxyfile($cm);

            return true;
        }
        return false;
    }

    public static function class_storage_exists(string $classname) : bool
    {
        $em = connection::get_em();
        if ($cm = self::get_cm($em, $classname)) {
            return $em->getConnection()->createSchemaManager()->tablesExist([$cm->getTableName()]);
        }
        return false;
    }
}
