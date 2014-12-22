<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\api\dbobject;
use midgard\portable\api\attachment;

class midgard_replicator
{
    /**
     * @return boolean Indicating success
     */
    public static function export(dbobject $object)
    {
        if (!mgd_is_guid($object->guid))
        {
            return false;
        }
        throw new Exception('not implemented');
        return true;
    }

    /**
     * @return boolean Indicating success
     */
    public static function export_by_guid($guid)
    {
        if (!mgd_is_guid($guid))
        {
            return false;
        }
        throw new Exception('not implemented');
        return true;
    }

    /**
     * @return string XML document containing purged object information
     */
    public static function export_purged($class, $startdate = null, $enddate = null)
    {
        throw new Exception('not implemented');
    }

    /**
     * @return string XML representation of the object
     */
    public static function serialize(dbobject $object)
    {
        throw new Exception('not implemented');
    }

    /**
     * @return string XML representation of the blob (content is base64 encoded)
     */
    public static function serialize_blob(attachment $object)
    {
        throw new Exception('not implemented');
    }

    /**
     * @return dbobject[] Array of objects read from input XML
     */
    public static function unserialize($xml, $force = false)
    {
        throw new Exception('not implemented');
    }

    /**
     * @return boolean Indicating success
     */
    public static function import_object($object, $force = false)
    {
        if (!mgd_is_guid($object->guid))
        {
            return false;
        }

        throw new Exception('not implemented');
    }

    /**
     * @return boolean Indicating success
     */
    public static function import_from_xml($xml, $force = false)
    {
        throw new Exception('not implemented');
    }
}