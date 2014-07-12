<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Mapping\MappingException;
use midgard\portable\storage\connection;

class midgard_storage
{
    public static function create_base_storage()
    {
        $em = connection::get_em();
        $factory = $em->getMetadataFactory();
        $classes = $factory->getAllMetadata();

        foreach ($classes as $class)
        {
            $stat = self::create_class_storage($class->getName());
            if (!$stat)
            {
                return false;
            }
        }

        $admin = $em->find('midgard:midgard_user', 1);

        if ($admin === null)
        {
            $fqcn = $em->getConfiguration()->getEntityNamespace("midgard") . "\\midgard_person";
            $person = new $fqcn;
            $person->firstname = 'Midgard';
            $person->lastname = 'Administrator';
            $person->create();

            $fqcn = $em->getConfiguration()->getEntityNamespace("midgard") . "\\midgard_user";
            $admin = new $fqcn;
            $admin->authtype = 'Plaintext';
            $admin->authtypeid = 2;
            $admin->login = 'admin';
            $admin->password = 'password';
            $admin->active = true;
            $admin->usertype = 2;
            $admin->set_person($person);
            $admin->create();
        }

        return true;
    }

    public static function create_class_storage($classname)
    {
        $em = connection::get_em();

        $cm = self::get_cm($em, $classname);
        if ($cm === false)
        {
            return false;
        }

        if (!$em->getConnection()->getSchemaManager()->tablesExist(array($cm->getTableName())))
        {
            $tool = new SchemaTool($em);
            $tool->createSchema(array($cm));
        }
        return true;
    }

    private static function get_cm($em, $classname)
    {
        if (!class_exists($classname))
        {
            // if the class doesn't exist (e.g. for some_random_string), there is really nothing we could do
            return false;
        }

        $factory = $em->getMetadataFactory();
        try
        {
            return $factory->getMetadataFor($classname);
        }
        catch (MappingException $e)
        {
            // add namespace
            $classname = $em->getConfiguration()->getEntityNamespace("midgard") . '\\' . $classname;
            try
            {
                return $factory->getMetadataFor($classname);
            }
            catch (MappingException $e)
            {
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
     *
     * @param string $classname The MgdSchema class to work on
     */
    public static function update_class_storage($classname)
    {
        $em = connection::get_em();
        $cm = self::get_cm($em, $classname);
        if ($cm === false)
        {
            return false;
        }
        $sm = $em->getConnection()->getSchemaManager();
        if ($sm->tablesExist(array($cm->getTableName())))
        {
            $tool = new SchemaTool($em);
            $conn = $em->getConnection();
            $from = $sm->createSchema();
            $to = $tool->getSchemaFromMetadata(array($cm));

            $comparator = new Comparator;
            $diff = $comparator->compare($from, $to);
            if (!empty($diff->changedTables[$cm->getTableName()]->removedColumns))
            {
                $diff->changedTables[$cm->getTableName()]->removedColumns = array();
            }
            $sql = $diff->toSaveSql($conn->getDatabasePlatform());


            foreach ($sql as $sql_line)
            {
                $conn->executeQuery($sql_line);
            }

            return true;
        }
        return false;
    }

    public static function delete_class_storage($classname)
    {
        return false;
    }

    public static function class_storage_exists($classname)
    {
        $em = connection::get_em();

        $cm = self::get_cm($em, $classname);
        if ($cm === false)
        {
            return false;
        }

        return $em->getConnection()->getSchemaManager()->tablesExist(array($cm->getTableName()));
    }
}