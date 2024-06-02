<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\mgdschema;

use SimpleXMLElement;

class type
{
    public $name;

    public $table;

    public string $extends = '\\midgard\\portable\\api\\mgdobject';

    public $primaryfield;

    public $parent;

    public $parentfield;

    public $upfield;

    public bool $has_metadata = true;

    public bool $links_as_entities = false;

    public array $subtypes = [];

    private array $dbfields = [];

    public array $field_aliases = [];

    /**
     * @var mixin[]
     */
    protected array $mixins = [];

    /**
     * @var property[]
     */
    private array $properties = [];

    public function __construct(SimpleXMLElement $attributes)
    {
        foreach ($attributes as $name => $value) {
            $value = (string) $value;
            if ($name == 'metadata') {
                $this->has_metadata = ($value === 'true');
            } elseif ($name == 'links_as_entities') {
                $this->links_as_entities = ($value === 'true');
            } else {
                $this->$name = $value;
            }
        }
    }

    public function add_property(node $property, string $name = null)
    {
        $name ??= $property->name;

        if ($property instanceof mixin) {
            $this->mixins[$name] = $property;
        } else {
            if ($property->parentfield) {
                $this->parentfield = $property->name;
                if (   empty($this->parent)
                    && $property->link) {
                    $this->parent = $property->link['target'];
                }
            }
            if (!isset($this->dbfields[$property->field])) {
                $this->properties[$name] = $property;
                $this->dbfields[$property->field] = $property->name;
            } elseif (!isset($this->properties[$property->name])) {
                $this->field_aliases[$property->name] = $this->dbfields[$property->field];
            }
        }
    }

    public function has_property(string $name) : bool
    {
        return isset($this->properties[$name]);
    }

    /**
     * @return property[]
     */
    public function get_properties() : array
    {
        return $this->properties;
    }

    /**
     * @return mixin[]
     */
    public function get_mixins() : array
    {
        return $this->mixins;
    }

    public function get_property(string $name) : node
    {
        //@todo Error reporting
        return $this->properties[$name];
    }
}
