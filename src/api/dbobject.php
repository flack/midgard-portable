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

    public function __set($field, $value)
    {
        $this->initialize();

        if (   !$this->cm->hasField($field)
            && array_key_exists($field, $this->cm->midgard['field_aliases']))
        {
            $field = $this->cm->midgard['field_aliases'][$field];
        }
        if ($this->cm->isSingleValuedAssociation($field))
        {
            // mgd api only allows setting links identifiers, doctrine wants objects,
            // so it seems we need an expensive and pretty useless conversion..
            if (empty($value))
            {
                $value = null;
            }
            else
            {
                $classname = $this->cm->getAssociationTargetClass($field);
                $value = connection::get_em()->getReference($classname, $value);
            }
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
            else if ($mapping['type'] === 'boolean')
            {
                $value = (boolean) $value;
            }
            else if ($mapping['type'] === 'float')
            {
                $value = (float) $value;
            }
            else if ($mapping['type'] === 'midgard_datetime')
            {
                if (   is_string($value)
                    && $value !== '0000-00-00 00:00:00')
                {
                    $value = new midgard_datetime($value);
                }
                else if (!($value instanceof midgard_datetime))
                {
                    $value = new midgard_datetime('0001-01-01 00:00:00');
                }
            }
        }
        else
        {
            //If property doesn't exist, we silently ignore it
            return;
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
        if (   $this->$field === null
            && $this->cm->isIdentifier($field))
        {
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