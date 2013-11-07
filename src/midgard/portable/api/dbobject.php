<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api;

use midgard\portable\storage\connection;
use Doctrine\Common\Persistence\ObjectManagerAware;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use midgard_datetime;

abstract class dbobject implements ObjectManagerAware
{
    protected $guid = '';

    /**
     *
     * @var Doctrine\Common\Persistence\Mapping\ClassMetadata
     */
    protected $cm;

    /**
     * {@inheritDoc}
     */
    public function injectObjectManager(ObjectManager $objectmanager, ClassMetadata $classmetadata)
    {
        if ($objectmanager !== connection::get_em()) {
            throw new \RuntimeException("Trying to use midgard_dbobject with different ObjectManager instances");
        }

        $this->cm = $classmetadata;
    }

    public function __call($method, $args)
    {
        throw new \BadMethodCallException("Unknown method " . $method . " on " . get_class($this));
    }

    public function __set($field, $value)
    {
        $this->initialize();

        if (   !$this->cm->hasField($field)
            && array_key_exists($field, $this->cm->midgard['field_aliases']))
        {
            $field = $this->cm->midgard['field_aliases'][$field];
        }
        // mgd api only allows setting links identifiers, doctrine wants objects,
        // so it seems we need an expensive and pretty useless conversion..
        if (   $this->cm->isSingleValuedAssociation($field)
            && $value !== null)
        {
            $classname = $this->cm->getAssociationTargetClass($field);
            $target = connection::get_em()->find($classname, $value);
            $value = $target;
        }
        else if ($this->cm->hasField($field))
        {
            $mapping = $this->cm->getFieldMapping($field);
            if ($mapping['type'] === 'string')
            {
                $value = (string) $value;
            }
            else if ($mapping['type'] === 'integer')
            {
                $value = (int) $value;
            }
            else if (   $mapping['type'] === 'midgard_datetime'
                     && !($value instanceof midgard_datetime))
            {
                $value = new midgard_datetime('0001-01-01 00:00:00');
            }
        }

        $this->$field = $value;
    }

    public function __get($field)
    {
        $this->initialize();

        if (   !$this->cm->hasField($field)
            && array_key_exists($field, $this->cm->midgard['field_aliases']))
        {
            $field = $this->cm->midgard['field_aliases'][$field];
        }

        if ($this->cm->isSingleValuedAssociation($field))
        {
            // mgd api only allows returning link identifiers, doctrine has objects,
            // so it seems we need a pretty useless conversion..
            if (is_object($this->$field))
            {
                return (int) $this->$field->id;
            }
            return 0;
        }

        return $this->$field;
    }

    public function __isset($field)
    {
        return property_exists($this, $field);
    }

    protected function populate_from_entity(dbobject $entity)
    {
        $this->initialize();
        foreach ($this->cm->reflFields as $name => $field)
        {
            if (   $entity->$name instanceof object
                && empty($entity->$name->id))
            {
                // This normally means that the target entity has been purged.
                // Midgard lets you keep the (broken) association, but we have to unset it,
                // becaue otherwise, Doctrine will throw exceptions during flush
                $entity->$name = null;
            }
            $this->$name = $entity->$name;
        }
    }

    protected function get_entity_instance($classname)
    {
        $em = connection::get_em();
        $fqn = $em->getConfiguration()->getEntityNamespace("midgard") . "\\" . $classname;
        $entity = new $fqn;
        $entity->set_guid(connection::generate_guid());
        return $entity;
    }

    protected function initialize()
    {
        if ($this->cm === null)
        {
            $this->cm = connection::get_em()->getClassMetadata(get_class($this));
        }
    }
}