<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\connection;

class midgard_object_class
{
    public static function factory($classname, $id = null)
    {
        $cm = connection::get_em()->getClassMetadata('midgard:' . $classname);
        $classname = $cm->name;
        return new $classname($id);
    }

    public static function undelete($guid)
    {

    }

    public static function get_object_by_guid($guid)
    {
        $qb = connection::get_em()->createQueryBuilder();
        $qb->from('midgard:midgard_repligard', 'r')
            ->select('r.typename')
            ->where('r.guid = ?1')
            ->setParameter(1, $guid);
        $type = $qb->getQuery()->getSingleScalarResult();
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