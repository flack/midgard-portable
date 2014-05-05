<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\connection;
use midgard\portable\storage\subscriber;
use midgard\portable\api\error\exception;
use midgard\portable\storage\objectmanager;

class midgard_object_class
{
    public static function resolve_classname($guid)
    {
        $qb = connection::get_em()->createQueryBuilder();
        $qb->from('midgard:midgard_repligard', 'r')
        ->addSelect('r.typename')
        ->addSelect('r.object_action')
        ->where('r.guid = ?1')
        ->setParameter(1, $guid);

        try
        {
            $result = $qb->getQuery()->getSingleResult();
        }
        catch (\Doctrine\ORM\NoResultException $e)
        {
            $result = null;
        }
        if ($result === null)
        {
            throw exception::not_exists();
        }
        if ($result["object_action"] == subscriber::ACTION_PURGE)
        {
            throw exception::object_purged();
        }

        return $result["typename"];
    }

    public static function resolve_fqn($classname)
    {
        $cm = connection::get_em()->getClassMetadata('midgard:' . $classname);
        return $cm->name;
    }

    public static function factory($classname, $id = null)
    {
        if ($classname === null)
        {
            return null;
        }
        $classname = self::resolve_fqn($classname);
        return new $classname($id);
    }

    public static function undelete($guid)
    {
        try
        {
            $classname = self::resolve_classname($guid);
        }
        catch(exception $e)
        {
            return false;
        }
        $classname = self::resolve_fqn($classname);

        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('guid', '=', $guid);
        $results = $qb->execute();
        if (count($results) === 0)
        {
            exception::not_exists();
            return false;
        }
        $entity = array_shift($results);

        if (!$entity->metadata_deleted)
        {
            exception::internal(new \Exception("Object is not deleted."));
            return false;
        }

        try
        {
            $om = new objectmanager(connection::get_em());
            $om->undelete($entity);
        }
        catch (\Exception $e)
        {
            exception::internal($e);
            return false;
        }

        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    public static function get_object_by_guid($guid)
    {
        $type = self::resolve_classname($guid);
        return self::factory($type, $guid);
    }

    public static function get_property_up($classname)
    {
        if (is_object($classname))
        {
            $classname = get_class($classname);
        }
        $cm = connection::get_em()->getClassMetadata($classname);
        return $cm->midgard['upfield'];
    }

    public static function get_property_parent($classname)
    {
        if (is_object($classname))
        {
            $classname = get_class($classname);
        }
        $cm = connection::get_em()->getClassMetadata($classname);
        return $cm->midgard['parentfield'];
    }

    public static function connect_default($classname, $signal, $callback, $userdata = null) // <== check!
    {

    }

    public static function has_metadata($classname)
    {

    }

    public static function get_schema_value($classname, $node_name)
    {

    }
}