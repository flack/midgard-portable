<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

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
            $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
            $tool->createSchema(array($cm));
        }
        return true;
    }

    private static function get_cm($em, $classname)
    {
        $factory = $em->getMetadataFactory();
        if ($factory->hasMetadataFor($classname))
        {
            return $factory->getMetadataFor($classname);
        }
        $factory->getAllMetadata();
        // add namespace
        $classname = $em->getConfiguration()->getEntityNamespace("midgard") . '\\' . $classname;
        if ($factory->hasMetadataFor($classname))
        {
            return $factory->getMetadataFor($classname);
        }
        // check for merged classes (duplicate tablenames)
        if (class_exists($classname))
        {
            $classname = get_class(new $classname);
            if ($factory->hasMetadataFor($classname))
            {
                return $factory->getMetadataFor($classname);
            }
        }
        // if the class doesn't exist (eg. for some_random_string), there is really nothing we could do
        return false;
    }

    public static function update_class_storage($classname)
    {
        $em = connection::get_em();
        $cm = self::get_cm($em, $classname);
        if ($cm === false)
        {
            return false;
        }

        if ($em->getConnection()->getSchemaManager()->tablesExist(array($cm->getTableName())))
        {
            $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
            $tool->updateSchema(array($cm), true);
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