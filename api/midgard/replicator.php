<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\api\dbobject;
use midgard\portable\api\attachment;
use midgard\portable\storage\connection;
use \midgard_datetime;
use \SimpleXMLElement;
use midgard\portable\storage\subscriber;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;

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
        $xml = new SimpleXMLElement('<midgard_object xmlns="http://www.midgard-project.org/midgard_object/1.8"/>');

        $cm = connection::get_em()->getClassMetadata(get_class($object));
        $node = $xml->addChild($cm->getReflectionClass()->getShortName());
        $node->addAttribute('guid', $object->guid);
        $node->addAttribute('purge', 'no');

        if (mgd_is_guid($object->guid))
        {
            $node->addAttribute('action', self::get_object_action($object->guid));
        }

        $metadata = array();

        foreach ($cm->getAssociationNames() as $name)
        {
            $node->addChild($name, self::resolve_link_id($cm, $object, $name));
        }
        foreach ($cm->getFieldNames() as $name)
        {
            if ($name == 'guid')
            {
                continue;
            }
            if (strpos($name, 'metadata_') === 0)
            {
                $metadata[substr($name, 9)] = $object->$name;
            }
            else
            {
                $node->addChild($name, self::convert_value($object->$name));
            }
        }

        if (!empty($metadata))
        {
            $mnode = $node->addChild('metadata');
            foreach ($metadata as $name => $value)
            {
                $mnode->addChild($name, self::convert_value($value));
            }
        }

        return $xml->asXML();
    }

    private static function resolve_link_id(ClassMetadata $cm, dbobject $object, $name)
    {
        if ($object->$name == 0)
        {
            return '0';
        }
        $target_class = $cm->getAssociationTargetClass($name);
        return connection::get_em()
            ->createQueryBuilder()
            ->from($target_class, 'c')
            ->select('c.guid')
            ->where('c.id = ?0')
            ->setParameter(0, $object->$name)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private static function get_object_action($guid)
    {
        $action = connection::get_em()
            ->createQueryBuilder()
            ->from('midgard:midgard_repligard', 'c')
            ->select('c.object_action')
            ->where('c.guid = ?0')
            ->setParameter(0, $guid)
            ->getQuery()
            ->getSingleScalarResult();

        switch ((int) $action)
        {
            case subscriber::ACTION_CREATE:
                return 'created';
            case subscriber::ACTION_UPDATE:
                return 'updated';
            case subscriber::ACTION_DELETE:
                return 'deleted';
            case subscriber::ACTION_PURGE:
                return 'purged';
            default:
                return 'none';
        }
    }

    private static function convert_value($value)
    {
        if ($value instanceof midgard_datetime)
        {
            return $value->format('Y-m-d H:i:sO');
        }
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