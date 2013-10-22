<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\mgdschema\property;
use midgard\portable\mgdschema\type;
use midgard\portable\mgdschema\mixin;
use SimpleXMLElement;

class xmlreader
{
    /**
     *
     * @var SimpleXMLElement
     */
    private $parser;

    /**
     *
     * @var array
     */
    private $mixins = array();

    public function parse($filename)
    {
        $this->parser = simplexml_load_file($filename);
        $this->parser->registerXPathNamespace('r', "http://www.midgard-project.org/repligard/1.4");
        return $this->parse_file();
    }

    private function parse_file()
    {
        $types = array();
        $nodes = $this->parser->xpath('/r:Schema/r:type');
        foreach ($nodes as $node)
        {
            $type = $this->parse_type($node);
            $types[$type->name] = $type;
        }
        return $types;
    }

    private function parse_type(SimpleXMLElement $node)
    {
        $type = new type($node->attributes());
        $node->registerXPathNamespace('r', "http://www.midgard-project.org/repligard/1.4");
        $properties = $node->xpath('r:property');
        foreach ($properties as $property)
        {
            $this->add_property($type, $property);
        }
        $guid = new property($type, 'guid', 'guid');
        $guid->unique = true;
        $type->add_property($guid);
        if ($type->has_metadata)
        {
            $this->add_mixin('metadata', $type);
        }
        return $type;
    }

    private function add_property(type $type, SimpleXMLElement $node, $prefix = '')
    {
        if ($prefix !== '')
        {
            $prefix .= '_';
        }
        $attributes = $node->attributes();
        $property_name = null;
        $property_type = null;
        $property_attributes = array();
        foreach ($attributes as $name => $value)
        {
            $value = (string) $value;
            switch ($name)
            {
                case 'primaryfield':
                case 'upfield':
                case 'parentfield':
                    $type->$name = (string) $value;
                    break;
                case 'name':
                    $property_name = $prefix . $value;
                    break;
                case 'type':
                    $property_type = $value;
                    break;
                default:
                    $property_attributes[$name] = $value;
                    break;
            }
        }

        $property = new property($type, $property_name, $property_type);

        $property->set_multiple($property_attributes);

        $type->add_property($property);
    }

    private function add_mixin($name, type $type)
    {
        if (empty($this->mixins[$name]))
        {
            $schema = simplexml_load_file(dirname(dirname(dirname(__DIR__))) . '/xml/' . $name . '.xml');
            $schema->registerXPathNamespace('r', 'http://www.midgard-project.org/repligard/1.4');
            $nodes = $schema->xpath('/r:Schema/r:mixin');
            $this->mixins[$name] = new mixin($nodes[0]->attributes());
            foreach ($schema->xpath('/r:Schema/r:mixin/r:property') as $property)
            {
                $this->add_property($this->mixins[$name], $property, $name);
            }
        }
        $type->add_property($this->mixins[$name], $name);
        foreach ($this->mixins[$name]->get_properties() as $field => $property)
        {
            $type->add_property($property, $field);
        }
    }
}