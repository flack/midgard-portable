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
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $factory = $em->getMetadataFactory();
        $classes = $factory->getAllMetadata();

        $tables = array();
        foreach ($classes as $class)
        {
            $tables[] = $class->getTableName();
        }

        if (!$em->getConnection()->getSchemaManager()->tablesExist($tables))
        {
            $tool->createSchema($classes);
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
        return false;
    }

    public static function update_class_storage($classname)
    {
        return false;
    }

    public static function delete_class_storage($classname)
    {
        return false;
    }

    public static function class_storage_exists($classname)
    {
        return false;
    }
}