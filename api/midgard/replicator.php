<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\api\dbobject;
use midgard\portable\api\attachment;
use midgard\portable\storage\connection;
use \midgard_datetime as midgard_datetime;
use \midgard_connection as midgard_connection;
use \SimpleXMLElement as SimpleXMLElement;
use midgard\portable\storage\subscriber;
use Doctrine\Persistence\Mapping\ClassMetadata;
use midgard\portable\api\error\exception;
use midgard\portable\api\blob;
use Doctrine\ORM\NoResultException;

class midgard_replicator
{
    public static function export(dbobject $object) : bool
    {
        if (!mgd_is_guid($object->guid)) {
            return false;
        }
        throw new Exception('not implemented');
        return true;
    }

    public static function export_by_guid(string $guid) : bool
    {
        if (!mgd_is_guid($guid)) {
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

        if ($result['object_action'] === subscriber::ACTION_PURGE) {
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
        return $result > 0;
    }

    /**
     * @return string XML document containing purged object information
     */
    public static function export_purged(string $class, $startdate = null, $enddate = null)
    {
        throw new Exception('not implemented');
    }

    /**
     * @return string XML representation of the object
     */
    public static function serialize(dbobject $object) : string
    {
        $xml = new SimpleXMLElement('<midgard_object xmlns="http://www.midgard-project.org/midgard_object/1.8"/>');

        $cm = connection::get_em()->getClassMetadata(get_class($object));
        $node = $xml->addChild($cm->getReflectionClass()->getShortName());
        $node->addAttribute('guid', $object->guid);
        $node->addAttribute('purge', 'no');

        if (mgd_is_guid($object->guid)) {
            $node->addAttribute('action', self::get_object_action($object->guid));
        }

        $metadata = [];

        foreach ($cm->getAssociationNames() as $name) {
            $node->addChild($name, self::resolve_link_id($cm, $object, $name));
        }
        foreach ($cm->getFieldNames() as $name) {
            if ($name == 'guid') {
                continue;
            }
            if (strpos($name, 'metadata_') === 0) {
                $metadata[substr($name, 9)] = $object->$name;
            } else {
                $node->addChild($name, self::convert_value($object->$name));
            }
        }

        if (!empty($metadata)) {
            $mnode = $node->addChild('metadata');
            foreach ($metadata as $name => $value) {
                $mnode->addChild($name, self::convert_value($value));
            }
        }

        return $xml->asXML();
    }

    /**
     * @return string XML representation of the blob (content is base64 encoded)
     */
    public static function serialize_blob(attachment $object) : string
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
    public static function unserialize(string $xml, bool $force = false) : array
    {
        $ret = [];

        $xml = new SimpleXMLElement($xml);
        foreach ($xml as $node) {
            try {
                if ($node->getName() == 'midgard_blob') {
                    $ret[] = self::blob_from_xml($node, $force);
                } else {
                    $ret[] = self::object_from_xml($node, $force);
                }
            } catch (\Exception $e) {
                connection::log()->warning($e->getMessage());
            }
        }

        return $ret;
    }

    public static function import_object(dbobject $object, bool $force = false) : bool
    {
        if (!mgd_is_guid($object->guid)) {
            midgard_connection::get_instance()->set_error(exception::INVALID_PROPERTY_VALUE);
            return false;
        }

        $classname = get_class($object);

        switch (self::get_object_action($object->guid)) {
            case 'created':
            case 'updated':
                $dbobject = new $classname($object->guid);
                break;

            case 'deleted':
                connection::get_em()->getFilters()->disable('softdelete');
                $dbobject = new $classname($object->guid);
                connection::get_em()->getFilters()->enable('softdelete');
                break;

            case 'purged':
                if (!$force) {
                    return false;
                }
                $result = connection::get_em()
                    ->createQueryBuilder()
                    ->delete('midgard:midgard_repligard', 'c')
                    ->where('c.guid = ?0')
                    ->setParameter(0, $object->guid)
                    ->getQuery()
                    ->execute();

                if ($result == 0) {
                    return false;
                }
                //fall-through

            default:
                $dbobject = new $classname;
                $dbobject->set_guid($object->guid);
                break;
        }

        if (   $dbobject->id > 0
            && $dbobject->metadata->revised->format('U') >= $object->metadata->revised->format('U')) {
            midgard_connection::get_instance()->set_error(exception::OBJECT_IMPORTED);
            return false;
        }

        if (   $dbobject->metadata->deleted
            && !$object->metadata->deleted) {
            if (!midgard_object_class::undelete($dbobject->guid)) {
                return false;
            }
            $dbobject->metadata_deleted = false;
        } elseif (   !$dbobject->metadata->deleted
                 && $object->metadata->deleted) {
            return $dbobject->delete();
        }

        $cm = connection::get_em()->getClassMetadata(get_class($object));

        foreach ($cm->getAssociationNames() as $name) {
            $dbobject->$name = self::resolve_link_guid($cm, $name, $object->$name);
        }
        foreach ($cm->getFieldNames() as $name) {
            if ($name == 'id') {
                continue;
            }
            if (strpos($name, 'metadata_') === false) {
                $dbobject->$name = $object->$name;
            }
        }
        $dbobject->metadata->imported = new \midgard_datetime();
        if ($dbobject->id > 0) {
            return $dbobject->update();
        }

        return $dbobject->create();
    }

    public static function import_from_xml(string $xml, bool $force = false)
    {
        $objects = self::unserialize($xml, $force);
        foreach ($objects as $object) {
            if ($object instanceof blob) {
                self::import_blob($object, $force);
            } else {
                self::import_object($object, $force);
            }
        }
    }

    private static function import_blob(blob $blob, bool $force)
    {
        $blob->write_content($blob->content);
    }

    private static function resolve_link_id(ClassMetadata $cm, dbobject $object, string $name) : string
    {
        if ($object->$name == 0) {
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

    private static function resolve_link_guid(ClassMetadata $cm, string $name, string $value) : int
    {
        if (!mgd_is_guid($value)) {
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

    private static function object_from_xml(SimpleXMLElement $node, bool $force) : dbobject
    {
        $cm = connection::get_em()->getClassMetadata('midgard:' . $node->getName());
        $classname = $cm->getName();
        $object = new $classname;
        $object->set_guid($node['guid']);
        $object->action = $node['action'];
        foreach ($node as $child) {
            $field = $child->getName();
            if ($field == 'metadata') {
                foreach ($child as $mchild) {
                    $field = 'metadata_' . $mchild->getName();
                    $object->$field = (string) $mchild;
                }
                continue;
            }
            $value = (string) $child;
            if ($cm->isSingleValuedAssociation($field)) {
                try {
                    $value = self::resolve_link_guid($cm, $field, $value);
                } catch (NoResultException $e) {
                    if (!$force) {
                        throw $e;
                    }
                    $value = 0;
                }
            }

            $object->$field = $value;
        }
        return $object;
    }

    private static function blob_from_xml(SimpleXMLElement $node, bool $force) : blob
    {
        $attachment = midgard_object_class::get_object_by_guid((string) $node['guid']);

        $blob = new blob($attachment);
        $blob->content = base64_decode($node);
        return $blob;
    }

    private static function get_object_action(string $guid) : string
    {
        $result = connection::get_em()
            ->createQueryBuilder()
            ->from('midgard:midgard_repligard', 'c')
            ->select('c.object_action')
            ->where('c.guid = ?0')
            ->setParameter(0, $guid)
            ->getQuery()
            ->getScalarResult();
        $action = (empty($result)) ? 0 : (int) $result[0]['object_action'];

        switch ($action) {
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
        if ($value instanceof midgard_datetime) {
            return $value->format('Y-m-d H:i:sO');
        }
        return $value;
    }
}
