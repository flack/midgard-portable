<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\mgdschema\manager;
use midgard\portable\mgdschema\type;
use midgard\portable\mgdschema\mixin;
use midgard\portable\mgdschema\property;
use midgard\portable\mgdschema\translator;

class classgenerator
{
    /**
     *
     * @var array
     */
    private $added_classes = array();

    /**
     *
     * @var string
     */
    private $output;

    /**
     *
     * @var string
     */
    private $filename;

    private $manager;

    /**
     *
     * @var boolean
     */
    private $dev_mode = false;

    public function __construct(manager $manager, $filename, $dev_mode = false)
    {
        $this->manager = $manager;
        $this->filename = $filename;
        $this->dev_mode = $dev_mode;
    }

    private function add_line($line)
    {
        $this->output .= $line;
        if ($this->dev_mode)
        {
            $this->output .= "\n";
        }
    }

    public function write($namespace = '')
    {
        if (file_exists($this->filename))
        {
            unlink($this->filename);
        }
        $types = $this->manager->get_types();
        $this->add_line('<?php ');

        uasort($types, function($a, $b)
        {
            if (   !empty($a->extends)
                && !empty($b->extends))
            {
                return strnatcmp($a->extends, $b->extends);
            }
            else if (!empty($a->extends))
            {
                return -1;
            }
            else if (!empty($b->extends))
            {
                return 1;
            }
            return 0;
        });

        if (!empty($namespace))
        {
            $this->add_line('namespace ' . $namespace . '; ');
            $this->add_line('use midgard\\portable\\api\\object as midgard_object; ');
            $this->add_line('use midgard_metadata; ');
            $this->add_line('use midgard\\portable\\api\\user as base_user; ');
            $this->add_line('use midgard\\portable\\api\\repligard as base_repligard; ');
            $this->add_line('use midgard\\portable\\api\\person as base_person; ');
            $this->add_line('use midgard\\portable\\api\\parameter as base_parameter; ');
            $this->add_line('use midgard\\portable\\api\\attachment as base_attachment; ');
            $this->add_line('use midgard_datetime; { ');
        }
        else
        {
            $this->add_line('use \midgard\portable\api\object; ');
            $this->add_line('use midgard\\portable\\api\\user as base_user; ');
            $this->add_line('use midgard\\portable\\api\\person as base_person; ');
            $this->add_line('use midgard\\portable\\api\\parameter as base_parameter; ');
            $this->add_line('use midgard\\portable\\api\\repligard as base_repligard; ');
            $this->add_line('use midgard\\portable\\api\\attachment as base_attachment; ');
        }
        foreach ($types as $type)
        {
            $this->convert_type($type, $namespace);
        }

        $this->register_aliases($namespace);

        if (!empty($namespace))
        {
            $this->add_line(' }');
        }

        //todo: midgard_blob special handling

        file_put_contents($this->filename, $this->output);
    }

    private function register_aliases($namespace)
    {
        $prefix = $this->get_class_prefix($namespace);

        foreach ($this->manager->get_types() as $name => $type)
        {
            if (   $prefix !== ''
                && !class_exists($type->name))
            {
                $this->add_line('class_alias( "' . $prefix . $type->name . '", "' . $type->name . '"); ');
            }
        }

        foreach ($this->manager->get_inherited_mapping() as $child => $parent)
        {
            $this->add_line('class_alias( "' . $prefix . $parent . '", "' . $prefix . $child . '"); ');
            if (   $prefix !== ''
                && !class_exists($child))
            {
                $this->add_line('class_alias( "' . $prefix . $parent . '", "' . $child . '"); ');
            }
        }
    }

    private function get_class_prefix($namespace)
    {
        if ($namespace === '')
        {
            return '';
        }
        return str_replace('\\', '\\\\', $namespace) . '\\\\';
    }

    private function convert_type(type $type, $namespace)
    {
        $this->begin_class($type);
        $objects = $this->write_properties($type);

        $this->write_constructor($type, $objects);

        $this->write_parent_getter($type);

        $this->end_class();
    }

    private function write_properties(type $type)
    {
        $objects = array();
        foreach ($type->get_mixins() as $name => $mixin)
        {
            $this->add_line(' protected $' . $name . ';');
        }

        foreach ($type->get_properties() as $name => $property)
        {
            if ($name == 'guid')
            {
                continue;
            }
            $line = ' protected $' . $name;
            $default = null;
            switch (translator::to_constant($property->type))
            {
                case translator::TYPE_BOOLEAN:
                    $default = 'false';
                    break;
                case translator::TYPE_FLOAT:
                    $default = '0.0';
                    break;
                case translator::TYPE_UINT:
                    if ($name == $type->primaryfield)
                    {
                        // no default value for identifier, because otherwise, Doctrine will think it's a detached entity
                        break;
                    }
                case translator::TYPE_INT:
                    $default = '0';
                    break;
                case translator::TYPE_GUID:
                case translator::TYPE_STRING:
                case translator::TYPE_LONGTEXT:
                    $default = "''";
                    break;
                case translator::TYPE_TIMESTAMP:
                    $objects[$name] = 'new midgard_datetime("0001-01-01 00:00:00")';
                    break;
            }
            if (   $default !== null
                   // we need to skip working links because in this case, Doctrine expects objects as values
                && (   !$property->link
                    || $this->manager->resolve_targetclass($property) === false))
            {
                $line .= ' = ' . $default;
            }
            $this->add_line($line . ';');
        }
        return $objects;
    }

    private function write_constructor(type $type, array $objects)
    {
        foreach ($type->get_mixins() as $name => $mixin)
        {
            //TODO: A magic getter should be configured with this somehow
            //$objects[$name] = 'new ' . $mixin->name . '($this)';
        }

        if (!empty($objects))
        {
            $this->add_line('public function __construct($id = null) {');
            $this->add_line('$this->init();');
            $this->add_line('parent::__construct($id);');
            $this->add_line('}');

            $this->add_line('public function init() {');
            foreach ($objects as $name => $code)
            {
                $this->add_line('$this->' . $name . ' = ' . $code . ';');
            }
            $this->add_line('}');
        }
    }

    private function write_parent_getter($type)
    {
        $candidates = array();

        if (!empty($type->upfield))
        {
            $candidates[] = $type->upfield;
        }
        if (!empty($type->parentfield))
        {
            $candidates[] = $type->parentfield;
        }
        if (empty($candidates))
        {
            return;
        }

        $this->add_line('public function get_parent() {');
        $this->add_line(' return $this->load_parent(' . var_export($candidates, true) . ');');
        $this->add_line('}');
    }

    private function begin_class(type $type)
    {
        $this->add_line('class ' . $type->name . ' extends ' . $type->extends);
        $mixins = $type->get_mixins();
        $interfaces = array_filter(array_map(function($name)
        {
            if (interface_exists('\\midgard\\portable\\storage\\' . $name . '\\entity'))
            {
                return '\\midgard\\portable\\storage\\' . $name . '\\entity';
            }
            return false;
        }, array_keys($mixins)));

        if (count($interfaces) > 0)
        {
            $this->add_line(' implements ' . implode(', ', $interfaces));
        }
        $this->add_line(' {');
    }

    private function end_class()
    {
        $this->add_line(' }');
    }
}