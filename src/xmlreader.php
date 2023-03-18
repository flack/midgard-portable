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
    private array $mixins = [];

    /**
     * @return type[]
     */
    public function parse(string $filename) : array
    {
        $types = [];
        $parser = simplexml_load_file($filename);
        $parser->registerXPathNamespace('r', "http://www.midgard-project.org/repligard/1.4");
        $nodes = $parser->xpath('/r:Schema/r:type');
        foreach ($nodes as $node) {
            $type = $this->parse_type($node);
            if (empty($type->table)) {
                throw new \LogicException('"table" attribute is missing in ' . $type->name . ' (' . $filename . ')');
            }
            $types[$type->name] = $type;
        }
        return $types;
    }

    private function parse_type(SimpleXMLElement $node) : type
    {
        $type = new type($node->attributes());
        $node->registerXPathNamespace('r', "http://www.midgard-project.org/repligard/1.4");
        $properties = $node->xpath('r:property');
        foreach ($properties as $property) {
            $this->add_property($type, $property);
        }
        $guid = new property($type, 'guid', 'guid');
        $guid->unique = true;
        $type->add_property($guid);
        if ($type->has_metadata) {
            $this->add_mixin('metadata', $type);
        }
        return $type;
    }

    private function add_property(type $type, SimpleXMLElement $node, string $prefix = '')
    {
        if ($prefix !== '') {
            $prefix .= '_';
        }
        $attributes = $node->attributes();
        $property_name = null;
        $property_type = null;
        $property_attributes = [];
        foreach ($attributes as $name => $value) {
            $value = (string) $value;
            switch ($name) {
                case 'primaryfield':
                case 'upfield':
                    $type->$name = $value;
                    break;
                case 'name':
                    $property_name = $prefix . $value;
                    break;
                case 'type':
                    $property_type = $value;
                    break;
                case 'field':
                    $value = $prefix . $value;
                    //fall-through
                default:
                    $property_attributes[$name] = $value;
                    break;
            }
        }
        $node->registerXPathNamespace('r', "http://www.midgard-project.org/repligard/1.4");
        $description = $node->xpath('r:description');
        if ($description) {
            $property_attributes['description'] = (string) $description[0];
        }

        $property = new property($type, $property_name, $property_type);
        $property->set_multiple($property_attributes);

        $type->add_property($property);
    }

    private function add_mixin(string $name, type $type)
    {
        if (empty($this->mixins[$name])) {
            $schema = simplexml_load_file(dirname(__DIR__) . '/xml/' . $name . '.xml');
            $schema->registerXPathNamespace('r', 'http://www.midgard-project.org/repligard/1.4');
            $nodes = $schema->xpath('/r:Schema/r:mixin');
            $this->mixins[$name] = new mixin($nodes[0]->attributes());
            foreach ($schema->xpath('/r:Schema/r:mixin/r:property') as $property) {
                $this->add_property($this->mixins[$name], $property, $name);
            }
        }
        $type->add_property($this->mixins[$name], $name);
        foreach ($this->mixins[$name]->get_properties() as $field => $property) {
            $type->add_property($property, $field);
        }
    }
}
