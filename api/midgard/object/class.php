<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\connection;
use midgard\portable\api\error\exception;
use midgard\portable\storage\objectmanager;
use Doctrine\ORM\Query;

class midgard_object_class
{
    public static function resolve_classname($guid)
    {
        $qb = connection::get_em()->createQueryBuilder();
        $qb->from('midgard:midgard_repligard', 'r')
        ->select('r.typename')
        ->where('r.guid = ?1')
        ->setParameter(1, $guid);

        // workaround for http://www.doctrine-project.org/jira/browse/DDC-2655
        try
        {
            $type = $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR);
        }
        catch (\Doctrine\ORM\NoResultException $e)
        {
            $type = null;
        }
        if ($type === null)
        {
            throw exception::not_exists();
        }
        return $type;
    }

    public static function factory($classname, $id = null)
    {
        $cm = connection::get_em()->getClassMetadata('midgard:' . $classname);
        $classname = $cm->name;
        return new $classname($id);
    }

    public static function undelete($guid)
    {
        $em = connection::get_em();
        $om = new objectmanager($em);

        $classname = self::resolve_classname($guid);
        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('guid', '=', $guid);
        $results = $qb->execute();
        if (count($results) === 0)
        {
            throw exception::not_exists();
        }
        $entity = array_shift($results);

        $om->undelete($entity);
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