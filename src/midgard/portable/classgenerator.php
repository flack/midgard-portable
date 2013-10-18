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

    public function __construct(manager $manager, $filename)
    {
    	$this->manager = $manager;
    	$this->filename = $filename;
    }

    public function write($namespace = '')
    {
        if (file_exists($this->filename))
        {
            unlink($this->filename);
        }
        $types = $this->manager->get_types();
        $this->output = '<?php ';

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
            $this->output .= 'namespace ' . $namespace . '; ';
            $this->output .= 'use \\midgard\\portable\\api\\object as midgard_object; ';
            $this->output .= 'use midgard_metadata; ';
            $this->output .= 'use \\midgard\\portable\\api\\user as base_user; ';
            $this->output .= 'use \\midgard\\portable\\api\\person as base_person; ';
            $this->output .= 'use midgard_datetime; { ';
        }
        else
        {
            $this->output .= 'use \midgard\portable\api\object; ';
        }
        foreach ($types as $type)
        {
            $this->convert_type($type);
        }

        foreach ($this->manager->get_inherited_mapping() as $child => $parent)
        {
            $this->write_inherited_mapping($child, $parent, $namespace);
        }

        if (!empty($namespace))
        {
            $this->output .= ' }';
        }

        //todo: midgard_blob special handling

        file_put_contents($this->filename, $this->output);
    }

    private function write_inherited_mapping($child, $parent, $namespace)
    {
        $parent = $this->get_class_prefix($namespace) . $parent;
        $child = $this->get_class_prefix($namespace) . $child;
        $this->output .= 'class_alias( "' . $parent . '", "' . $child . '"); ';
    }

    private function get_class_prefix($namespace)
    {
        if ($namespace === '')
        {
            return '';
        }
        return str_replace('\\', '\\\\', $namespace) . '\\\\';
    }

    private function convert_type(type $type)
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
            $this->output .= ' protected $' . $name . ';';
        }

        foreach ($type->get_properties() as $name => $property)
        {
            if ($name == 'guid')
            {
                continue;
            }
            $this->output .= ' protected $' . $name;
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
            	case translator::TYPE_INT:
            	    $default = '0';
            	    break;
            	case translator::TYPE_GUID:
            	case translator::TYPE_STRING:
            	case translator::TYPE_LONGTEXT:
            	    $default = "''";
            	    break;
            	case translator::TYPE_TIMESTAMP:
            	    $objects[$name] = 'new midgard_datetime';
            	    break;
            }
            if (   $default !== null
                   // we need to skip working links because in this case, Doctrine expects objects as values
                && (   !$property->link
                    || $this->manager->resolve_targetclass($property) === false))
            {
                $this->output .= ' = ' . $default;
            }
            $this->output .= ';';
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
            $this->output .= 'public function __construct($id = null) {';
            foreach ($objects as $name => $code)
            {
                $this->output .= '$this->' . $name . ' = ' . $code . ';';
            }
            $this->output .= 'parent::__construct($id);';
            $this->output .= '}';
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

        $this->output .= 'public function get_parent() {';
        $this->output .= ' return $this->load_parent(' . var_export($candidates, true) . ');';
        $this->output .= '}';
    }

    private function begin_class(type $type)
    {
        $this->output .= 'class ' . $type->name . ' extends ' . $type->extends;
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
            $this->output .= ' implements ' . implode(', ', $interfaces);
        }
        $this->output .= ' {';
    }

    private function end_class()
    {
        $this->output .= ' }';
    }
}