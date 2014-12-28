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
use \midgard_connection;
use \SimpleXMLElement;
use midgard\portable\storage\subscriber;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use midgard\portable\api\error\exception;
use midgard\portable\api\blob;
use midgard\portable\storage\objectmanager;

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
            midgard_connection::get_instance()->set_error(exception::INVALID_PROPERTY_VALUE);
            return false;
        }
        $result = connection::get_em()
            ->createQueryBuilder()
            ->from('midgard:midgard_repligard', 'c')
            ->select('c.typename', 'c.object_action')
            ->where('c.guid = ?0')
            ->setParameter(0, $guid)
            ->getQuery()
            ->getSingleResult();

        if ($result['object_action'] === subscriber::ACTION_PURGE)
        {
            midgard_connection::get_instance()->set_error(exception::OBJECT_PURGED);
            return false;
        }

        $result = connection::get_em()
            ->createQueryBuilder()
            ->update('midgard:' . $result['typename'], 'c')
            ->set('c.metadata_exported', '?0')
            ->setParameter(0, new midgard_datetime)
            ->where('c.guid = ?1')
            ->setParameter(1, $guid)
            ->getQuery()
            ->execute();

        midgard_connection::get_instance()->set_error(exception::OK);
        return ($result > 0);
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

    /**
     * @return string XML representation of the blob (content is base64 encoded)
     */
    public static function serialize_blob(attachment $object)
    {
        $blob = new blob($object);
        $xml = new SimpleXMLElement('<midgard_object xmlns="http://www.midgard-project.org/midgard_object/1.8"/>');
        $node = $xml->addChild('midgard_blob', base64_encode($blob->read_content()));
        $node->addAttribute('guid', $object->guid);

        return $xml->asXML();
    }

    /**
     * @return dbobject[] Array of objects read from input XML
     */
    public static function unserialize($xml, $force = false)
    {
        $ret = array();

        $xml = new SimpleXMLElement($xml);
        foreach ($xml as $node)
        {
            $ret[] = self::object_from_xml($node);
        }

        return $ret;
    }

    /**
     * @return boolean Indicating success
     */
    public static function import_object($object, $force = false)
    {
        if (!mgd_is_guid($object->guid))
        {
            midgard_connection::get_instance()->set_error(exception::INVALID_PROPERTY_VALUE);
            return false;
        }

        $cm = connection::get_em()->getClassMetadata(get_class($object));
        $classname = $cm->getName();

        connection::get_em()->getFilters()->disable('softdelete');
        try
        {
            $dbobject = new $classname($object->guid);
            connection::get_em()->getFilters()->enable('softdelete');
        }
        catch (exception $e)
        {
            connection::get_em()->getFilters()->enable('softdelete');
            if (   $e->getCode() === exception::NOT_EXISTS
                || $e->getCode() === exception::OK)
            {
                $object->metadata->imported = new \midgard_datetime();
                $object->id = 0;
                return $object->create();
            }
            return false;
        }

        if ($dbobject->metadata->revised >= $object->metadata->revised)
        {
            midgard_connection::get_instance()->set_error(exception::OBJECT_IMPORTED);
            return false;
        }

        if (   $dbobject->metadata->deleted
            && !$object->metadata->deleted)
        {
            if (!midgard_object_class::undelete($dbobject->guid))
            {
                return false;
            }
            $dbobject->metadata_deleted = false;
        }
        else if (   !$dbobject->metadata->deleted
                 && $object->metadata->deleted)
        {
            return $dbobject->delete();
        }

        foreach ($cm->getAssociationNames() as $name)
        {
            $dbobject->$name = self::resolve_link_guid($cm, $name, $object->$name);
        }
        foreach ($cm->getFieldNames() as $name)
        {
            if ($name == 'id')
            {
                continue;
            }
            if (strpos($name, 'metadata_') === false)
            {
                $dbobject->$name = $object->$name;
            }
        }
        $dbobject->metadata->imported = new \midgard_datetime();
        return $dbobject->update();
    }

    /**
     * @return boolean Indicating success
     */
    public static function import_from_xml($xml, $force = false)
    {
        $objects = self::unserialize($xml, $force);
        foreach ($objects as $object)
        {
            self::import_object($object, $force);
        }
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

    private static function resolve_link_guid(ClassMetadata $cm, $name, $value)
    {
        if (!mgd_is_guid($value))
        {
            return 0;
        }
        $target_class = $cm->getAssociationTargetClass($name);
        return connection::get_em()
            ->createQueryBuilder()
            ->from($target_class, 'c')
            ->select('c.id')
            ->where('c.guid = ?0')
            ->setParameter(0, $value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     *
     * @param SimpleXMLElement $node
     * @return dbobject
     */
    private static function object_from_xml(SimpleXMLElement $node)
    {
        $cm = connection::get_em()->getClassMetadata('midgard:' . $node->getName());
        $classname = $cm->getName();
        $object = new $classname;
        $object->set_guid($node['guid']);
        $object->action = $node['action'];
        foreach ($node as $child)
        {
            $field = $child->getName();
            if ($field == 'metadata')
            {
                foreach ($child as $mchild)
                {
                    $field = 'metadata_' . $mchild->getName();
                    $object->$field = (string) $mchild;
                }
                continue;
            }
            $value = (string) $child;
            if ($cm->isSingleValuedAssociation($field))
            {
                $value = self::resolve_link_guid($cm, $field, $value);
            }

            $object->$field = $value;
        }
        return $object;
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
        return $value;
    }
}