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
use midgard\portable\storage\interfaces\metadata;
use midgard\portable\api\dbobject;

class midgard_object_class
{
    private static function resolve_classname(string $guid, bool $include_deleted = false) : string
    {
        $qb = connection::get_em()->createQueryBuilder();
        $qb->from(connection::get_fqcn('midgard_repligard'), 'r')
            ->select('r.typename, r.object_action')
            ->where('r.guid = ?1')
            ->setParameter(1, $guid);

        try {
            $result = $qb->getQuery()->getSingleResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            throw exception::not_exists();
        }

        if ($result["object_action"] == subscriber::ACTION_PURGE) {
            throw exception::object_purged();
        }
        if (!$include_deleted && $result["object_action"] == subscriber::ACTION_DELETE) {
            throw exception::object_deleted();
        }
        if ($include_deleted && !self::has_metadata($result["typename"])) {
            throw exception::invalid_property_value();
        }

        return $result["typename"];
    }

    public static function factory(?string $classname, $id = null) : ?dbobject
    {
        if ($classname === null) {
            return null;
        }
        $classname = connection::get_fqcn($classname);
        if (!class_exists($classname)) {
            throw exception::invalid_object();
        }
        return new $classname($id);
    }

    public static function undelete(string $guid) : bool
    {
        try {
            $classname = self::resolve_classname($guid, true);
        } catch (exception $e) {
            return false;
        }
        $classname = connection::get_fqcn($classname);

        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('guid', '=', $guid);
        $results = $qb->execute();
        if (empty($results)) {
            exception::not_exists();
            return false;
        }
        $entity = $results[0];

        if (!$entity->metadata_deleted) {
            exception::internal(new \Exception("Object is not deleted."));
            return false;
        }

        try {
            $om = new objectmanager(connection::get_em());
            $om->undelete($entity);
        } catch (\Exception $e) {
            exception::internal($e);
            return false;
        }

        return true;
    }

    public static function get_object_by_guid(string $guid) : dbobject
    {
        if (!mgd_is_guid($guid)) {
            throw exception::not_exists();
        }

        $type = self::resolve_classname($guid);
        return self::factory($type, $guid);
    }

    public static function get_property_up(string $classname) : ?string
    {
        $cm = connection::get_em()->getClassMetadata($classname);
        return $cm->midgard['upfield'];
    }

    public static function get_property_parent(string $classname) : ?string
    {
        $cm = connection::get_em()->getClassMetadata($classname);
        return $cm->midgard['parentfield'];
    }

    public static function has_metadata($classname) : bool
    {
        if (is_string($classname)) {
            return in_array(metadata::class, class_implements($classname));
        }
        if (is_object($classname)) {
            return $classname instanceof metadata;
        }
        return false;
    }
}
