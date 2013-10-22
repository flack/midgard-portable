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

abstract class dbobject implements ObjectManagerAware
{
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

        // mgd api only allows setting links identifiers, doctrine wants objects,
        // so it seems we need an expensive and pretty useless conversion..
        if (   $this->cm->isSingleValuedAssociation($field)
            && $value !== null)
        {
            $classname = $this->cm->getAssociationTargetClass($field);
            $target = connection::get_em()->find($classname, $value);
            $value = $target;
        }

        $this->$field = $value;
    }

    public function __get($field)
    {
        $this->initialize();

        // mgd api only allows returning link identifiers, doctrine has objects,
        // so it seems we need a pretty useless conversion..
        if (   $this->cm->isSingleValuedAssociation($field)
            && is_object($this->$field))
        {
            return $this->$field->id;
        }

        return $this->$field;
    }

    public function __isset($field)
    {
        return isset($this->$field);
    }

    protected function populate_from_entity(dbobject $entity)
    {
        $this->initialize();
        foreach ($this->cm->reflFields as $name => $field)
        {
            $this->$name = $entity->$name;
        }
    }

    protected function initialize()
    {
        if ($this->cm === null)
        {
            $this->cm = connection::get_em()->getClassMetadata(get_class($this));
        }
    }
}