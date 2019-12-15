<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\connection;

class midgard_reflector_object
{
    public static function get_property_primary($classname)
    {
        return 'id';
    }

    public static function get_property_up($classname)
    {
        return midgard_object_class::get_property_up($classname);
    }

    public static function get_property_parent($classname)
    {
        return midgard_object_class::get_property_parent($classname);
    }

    public static function get_property_unique($classname)
    {
        $cm = connection::get_em()->getClassMetadata($classname);
        return $cm->midgard['unique_fields'][0] ?? null;
    }

    public static function list_children($classname)
    {
        $cm = connection::get_em()->getClassMetadata($classname);
        if (empty($cm->midgard['childtypes'])) {
            return [];
        }
        // @todo We filter out useful information (parent field name) in the name of mgd2 compat.
        return array_fill_keys(array_keys($cm->midgard['childtypes']), '');
    }

    public static function has_metadata_class($classname)
    {
        return midgard_object_class::has_metadata($classname);
    }

    public static function get_metadata_class($classname)
    {
        if (!self::has_metadata_class($classname)) {
            return null;
        }
        return 'midgard_metadata';
    }
}