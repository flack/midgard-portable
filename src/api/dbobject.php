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
     * @var ClassMetadata
     */
    protected $cm;

    /**
     * Simple map of association fields changed during the object's lifetime
     *
     * We need this for some workarounds for proxy-related problems during changeset calculation
     *
     * @var array
     */
    protected $changed_associations = array();

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

    /**
     * @return array
     */
    public function __get_changed_associations()
    {
        return $this->changed_associations;
    }

    /**
     * Filter out internal stuff for var_dump
     *
     * This is not 100% accurate right now (e.g. metadata is not handled totally correctly), but at least it
     * prevents killing the server by dumping recursively linked EntityManagers and the like
     *
     * @return array
     */
    public function __debugInfo()
    {
        $this->initialize();
        $properties = array_merge($this->cm->getFieldNames(), $this->cm->getAssociationNames(), array_keys($this->cm->midgard['field_aliases']));
        $properties = array_filter($properties, function ($input) {
            return (strpos($input, 'metadata_') === false);
        });
        $ret = array();
        foreach ($properties as $property) {
            $ret[$property] = $this->__get($property);
        }

        return $ret;
    }

    public function __set($field, $value)
    {
        $this->initialize();

        if (   !$this->cm->hasField($field)
            && array_key_exists($field, $this->cm->midgard['field_aliases'])) {
            $field = $this->cm->midgard['field_aliases'][$field];
        }
        if ($this->cm->isSingleValuedAssociation($field)) {
            // mgd api only allows setting links identifiers, doctrine wants objects,
            // so it seems we need an expensive and pretty useless conversion..
            if (empty($value)) {
                $value = null;
            } else {
                if (   !is_object($this->$field)
                    || $this->$field->id != $value) {
                    $this->changed_associations[$field] = true;
                }
                $classname = $this->cm->getAssociationTargetClass($field);
                $value = connection::get_em()->getReference($classname, $value);
            }
        } elseif ($this->cm->hasField($field)) {
            $mapping = $this->cm->getFieldMapping($field);

            if (   $mapping['type'] === 'string'
                || $mapping['type'] == 'text') {
                $value = (string) $value;
            } elseif ($mapping['type'] === 'integer') {
                $value = (int) $value;
            } elseif ($mapping['type'] === 'boolean') {
                $value = (boolean) $value;
            } elseif ($mapping['type'] === 'float') {
                $value = (float) $value;
            } elseif ($mapping['type'] === 'midgard_datetime') {
                if (   is_string($value)
                    && $value !== '0000-00-00 00:00:00') {
                    $value = new midgard_datetime($value);
                } elseif (!($value instanceof midgard_datetime)) {
                    $value = new midgard_datetime('0001-01-01 00:00:00');
                }
            }
        }

        $this->$field = $value;
    }

    public function __get($field)
    {
        $this->initialize();

        if (   !$this->cm->hasField($field)
            && array_key_exists($field, $this->cm->midgard['field_aliases'])) {
            $field = $this->cm->midgard['field_aliases'][$field];
        }

        if ($this->cm->isSingleValuedAssociation($field)) {
            // mgd api only allows returning link identifiers, doctrine has objects,
            // so it seems we need a pretty useless conversion..
            if (is_object($this->$field)) {
                return (int) $this->$field->id;
            }
            return 0;
        }
        if (   $this->$field === null
            && $this->cm->isIdentifier($field)) {
            return 0;
        }
        if (   $this->$field instanceof midgard_datetime
            && $this->$field->format('U') == -62169984000) {
            //This is mainly needed for working with converted Legacy databases. Midgard2 somehow handles this internally
            //@todo Find a nicer solution and research how QB handles this
            $this->$field->setDate(1, 1, 1);
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
        foreach ($this->cm->reflFields as $name => $field) {
            $this->$name = $entity->$name;
        }
    }

    protected function initialize()
    {
        if ($this->cm === null) {
            $this->cm = connection::get_em()->getClassMetadata(get_class($this));
        }
    }
}
