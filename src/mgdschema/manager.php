<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\mgdschema;

use midgard\portable\xmlreader;
use midgard\portable\storage\connection;

class manager
{
    /**
     *
     * @var array
     */
    private $types;

    /**
     *
     * @var array
     */
    private $schemadirs;

    /**
     *
     * @var string
     */
    private $namespace;

    /**
     *
     * @var array
     */
    private $merged_types = [];

    /**
     *
     * @var array
     */
    private $child_classes = [];

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
        if (isset($this->child_classes[$typename])) {
            return $this->child_classes[$typename];
        }
        return [];
    }

    public function resolve_targetclass(property $property)
    {
        $this->initialize();

        $fqcn = $this->get_fcqn($property->link['target']);

        if (   isset($this->types[$fqcn])
            || $property->link['target'] === $property->get_parent()->name) {
            return $property->link['target'];
        }
        if (!isset($this->merged_types[$property->link['target']])) {
            throw new \Exception('Link to unknown class ' . $property->link['target']);
        }
        return $this->merged_types[$property->link['target']];
    }

    private function initialize()
    {
        if ($this->types !== null) {
            return;
        }
        $reader = new xmlreader;
        $types = $reader->parse(dirname(__DIR__, 2) . '/xml/core.xml');

        foreach ($this->schemadirs as $schemadir) {
            foreach (glob($schemadir . '*.xml', GLOB_NOSORT) as $filename) {
                if (!file_exists($filename)) {
                    connection::log()->warning('File exists check for ' . $filename . ' returned false, skipping');
                    continue;
                }
                $types = array_merge($types, $reader->parse($filename));
            }
        }

        $tablemap = [];
        foreach ($types as $name => $type) {
            if ($type->parent) {
                $this->register_child_class($type);
            }

            if (!isset($tablemap[$type->table])) {
                $tablemap[$type->table] = [];
            }
            $tablemap[$type->table][] = $type;
        }

        foreach ($tablemap as $name => $types) {
            if (count($types) == 1) {
                $this->add_type($types[0]);
                unset($tablemap[$name]);
            }
        }

        // We need to process those separately, to be sure the targets for rewriting links are present
        while ($types = array_pop($tablemap)) {
            if (!$this->create_merged_types($types)) {
                array_push($types, $tablemap);
            }
        }
    }

    private function register_child_class(type $type)
    {
        if (!isset($this->child_classes[$type->parent])) {
            $this->child_classes[$type->parent] = [];
        }
        $this->child_classes[$type->parent][$type->name] = $type->parentfield;
    }

    /**
     * This sort of provides a workaround for situations where two tables use the same name
     */
    private function create_merged_types(array $types)
    {
        $root_type = null;
        foreach ($types as $i => $type) {
            // TODO: We should have a second pass here that prefers classnames starting with midgard_
            if ($type->extends === '\\midgard\\portable\\api\\mgdobject') {
                $root_type = $type;
                unset($types[$i]);
                break;
            }
        }
        if (empty($root_type)) {
            throw new \Exception('could not determine root type of merged group');
        }

        foreach ($types as $type) {
            foreach ($type->get_properties() as $property) {
                if ($root_type->has_property($property->name)) {
                    $root_property = $root_type->get_property($property->name);
                    if ($root_property->field !== $property->field) {
                        connection::log()->error('Naming collision in ' . $root_type->name . ': Field ' . $type->name . '.' . $property->name . ' cannot use column ' . $property->field);
                    }
                    if ($root_property->type !== $property->type) {
                        connection::log()->warn('Naming collision in ' . $root_type->name . ': Field ' . $type->name . '.' . $property->name . ' cannot use type ' . $property->type);
                    }
                    continue;
                }
                $root_type->add_property($property);
            }

            if (isset($this->child_classes[$type->name])) {
                foreach ($this->child_classes[$type->name] as $childname => $parentfield) {
                    $child_type = $this->get_type_by_shortname($childname);
                    if ($child_type === null) {
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
        $fqcn = $this->get_fcqn($classname);
        if (!isset($this->types[$fqcn])) {
            if (!isset($this->merged_types[$classname])) {
                return null;
            }
            $fqcn = $this->get_fcqn($this->merged_types[$classname]);
        }
        return $this->types[$fqcn];
    }

    private function add_type(type $type)
    {
        $classname = $type->name;
        // TODO: This should probably be in classgenerator
        if ($classname === 'midgard_user') {
            $type->extends = 'base_user';
        }
        if ($classname === 'midgard_person') {
            $type->extends = 'base_person';
        }
        if ($classname === 'midgard_parameter') {
            $type->extends = 'base_parameter';
        }
        if ($classname === 'midgard_repligard') {
            $type->extends = 'base_repligard';
        }
        if ($classname === 'midgard_attachment') {
            $type->extends = 'base_attachment';
        }
        $this->types[$this->get_fcqn($classname)] = $type;
    }

    private function get_fcqn($classname)
    {
        if (!empty($this->namespace)) {
            return $this->namespace . '\\' . $classname;
        }
        return $classname;
    }
}
