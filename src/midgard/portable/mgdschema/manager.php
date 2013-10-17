<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\mgdschema;

use midgard\portable\xmlreader;
use midgard\portable\mgdschema\type;
use midgard\portable\mgdschema\property;

class manager
{
    private $types;

    private $schemadirs;

    private $namespace;

    private $inherited_mapping = array();

    public function __construct(array $schemadirs, $namespace)
    {
        $this->schemadirs = $schemadirs;
        $this->namespace = $namespace;
    }

    public function get_types()
    {
        $this->initialize();
        return $this->types;
    }

    public function get_inherited_mapping()
    {
        $this->initialize();
        return $this->inherited_mapping;
    }

    public function resolve_targetclass(property $property)
    {
        $this->initialize();

        $target_class = $property->link['target'];
        if (   !array_key_exists($target_class, $this->types)
            && $target_class !== $property->get_parent()->name)
        {
            if (!array_key_exists($target_class, $this->inherited_mapping))
            {
                //TODO: This happens when loading classes individually, f.x. in unit tests. Should we care?
                return false;
                throw new \Exception('Link to unknown class ' . $target_class);
            }
            $target_class = $this->inherited_mapping[$target_class];
        }
        return $target_class;
    }

    private function initialize()
    {
        if ($this->types !== null)
        {
            return;
        }
        $reader = new xmlreader;
        $types = $reader->parse(dirname(dirname(dirname(dirname(__DIR__)))) . '/xml/core.xml');

        foreach ($this->schemadirs as $schemadir)
        {
            foreach (glob($schemadir . '*.xml', GLOB_NOSORT) as $filename)
            {
                $types = array_merge($types, $reader->parse($filename));
            }
        }

        $tablemap = array();
        foreach ($types as $name => $type)
        {
            if (!array_key_exists($type->table, $tablemap))
            {
                $tablemap[$type->table] = array();
            }
            $tablemap[$type->table][] = $type;
        }

        foreach ($tablemap as $name => $types)
        {
            if (count($types) == 1)
            {
                $this->add_type($types[0]);
            }
            else
            {
                $this->create_inherited_types($types);
            }
        }
    }

    /**
     * This sort of provides a workaround for situations where two tables use the same name
     */
    private function create_inherited_types(array $types)
    {
        $root_type = null;
        foreach ($types as $i => $type)
        {
            // TODO: We should have a second pass here that prefers classnames starting with midgard_
            if ($type->extends === 'midgard_object')
            {
                $root_type = $type;
                unset($types[$i]);
                break;
            }
        }
        if (empty($root_type))
        {
            throw new \Exception('could not determine root type of inheritance group');
        }

        foreach ($types as $type)
        {
            foreach ($type->get_properties() as $property)
            {
                if (!$root_type->has_property($property->name))
                {
                    $root_type->add_property($property);
                }
            }
            $this->inherited_mapping[$type->name] = $root_type->name;
        }
        $this->add_type($root_type);
    }

    private function add_type(type $type)
    {
        $classname = $type->name;
        if (!empty($this->namespace))
        {
            // TODO: This should be in classgenerator
            if ($classname === 'midgard_user')
            {
                $type->extends = 'base_user';
            }
            if ($classname === 'midgard_person')
            {
                $type->extends = 'base_person';
            }
            $classname = $this->namespace . '\\' . $classname;
        }
        $this->types[$classname] = $type;
    }
}