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

    private $merged_types = array();

    private $child_classes = array();

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
        return $this->merged_types;
    }

    public function get_child_classes($typename)
    {
        $this->initialize();
        if (array_key_exists($typename, $this->child_classes))
        {
            return $this->child_classes[$typename];
        }
        return array();
    }

    public function resolve_targetclass(property $property)
    {
        $this->initialize();

        $fqcn = $property->link['target'];
        if (!empty($this->namespace))
        {
            $fqcn = $this->namespace . '\\' . $fqcn;
        }

        if (   array_key_exists($fqcn, $this->types)
            || $property->link['target'] === $property->get_parent()->name)
        {
            $target_class = $property->link['target'];
        }
        else
        {
            if (!array_key_exists($property->link['target'], $this->merged_types))
            {
                throw new \Exception('Link to unknown class ' . $property->link['target']);
            }
            $target_class = $this->merged_types[$property->link['target']];
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
            if ($type->parent)
            {
                $this->register_child_class($type);
            }

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
                unset($tablemap[$name]);
            }
        }

        // We need to process those separately, to be sure the targets for rewriting links are present
        while ($types = array_pop($tablemap))
        {
            if (!$this->create_merged_types($types))
            {
                array_push($types, $tablemap);
            }
        }
    }

    private function register_child_class(type $type)
    {
        if (!array_key_exists($type->parent, $this->child_classes))
        {
            $this->child_classes[$type->parent] = array();
        }
        $this->child_classes[$type->parent][$type->name] = $type->parentfield;
    }

    /**
     * This sort of provides a workaround for situations where two tables use the same name
     */
    private function create_merged_types(array $types)
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
            throw new \Exception('could not determine root type of merged group');
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

            if (array_key_exists($type->name, $this->child_classes))
            {
                foreach ($this->child_classes[$type->name] as $childname => $parentfield)
                {
                    $child_type = $this->get_type_by_shortname($childname);
                    if ($child_type === null)
                    {
                        return false;
                    }

                    $child_type->parent = $root_type->name;
                    $this->register_child_class($child_type);
                }
                unset($this->child_classes[$type->name]);
            }
            $this->merged_types[$type->name] = $root_type->name;
        }
        $this->add_type($root_type);
        return true;
    }

    private function get_type_by_shortname($classname)
    {
        $fqcn = $classname;
        if (!empty($this->namespace))
        {
            $fqcn = $this->namespace . '\\' . $classname;
        }
        if (array_key_exists($fqcn, $this->types))
        {
            return $this->types[$fqcn];
        }
        else if (array_key_exists($classname, $this->merged_types))
        {
            $fqcn = $this->merged_types[$classname];
            if (!empty($this->namespace))
            {
                $fqcn = $this->namespace . '\\' . $fqcn;
            }
            return $this->types[$fqcn];
        }
        return null;
    }

    private function add_type(type $type)
    {
        $classname = $type->name;
        // TODO: This should probably be in classgenerator
        if ($classname === 'midgard_user')
        {
            $type->extends = 'base_user';
        }
        if ($classname === 'midgard_person')
        {
            $type->extends = 'base_person';
        }
        if ($classname === 'midgard_parameter')
        {
            $type->extends = 'base_parameter';
        }
        if ($classname === 'midgard_repligard')
        {
            $type->extends = 'base_repligard';
        }
        if ($classname === 'midgard_attachment')
        {
            $type->extends = 'base_attachment';
        }
        if (!empty($this->namespace))
        {
            $classname = $this->namespace . '\\' . $classname;
        }
        $this->types[$classname] = $type;
    }
}