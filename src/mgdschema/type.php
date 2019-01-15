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

    public $extends = '\\midgard\\portable\\api\\mgdobject';

    public $primaryfield;

    public $parent;

    public $parentfield;

    public $upfield;

    public $has_metadata = true;

    public $subtypes = [];

    private $dbfields = [];

    public $field_aliases = [];

    /**
     * @var mixin[]
     */
    protected $mixins = [];

    /**
     * @var property[]
     */
    private $properties = [];

    public function __construct(SimpleXMLElement $attributes)
    {
        foreach ($attributes as $name => $value) {
            if ($name == 'metadata') {
                $this->has_metadata = ($value === 'true');
            } else {
                $this->$name = (string) $value;
            }
        }
    }

    public function add_property(node $property, $name = null)
    {
        if ($name === null) {
            $name = $property->name;
        }

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

    public function has_property($name)
    {
        return isset($this->properties[$name]);
    }

    /**
     * @return property[]
     */
    public function get_properties()
    {
        return $this->properties;
    }

    /**
     * @return mixin[]
     */
    public function get_mixins()
    {
        return $this->mixins;
    }

    /**
     *
     * @param string $name
     * @return node
     */
    public function get_property($name)
    {
        //@todo Error reporting
        return $this->properties[$name];
    }
}
