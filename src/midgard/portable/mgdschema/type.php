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

    public $extends = 'midgard_object';

    public $primaryfield;

    public $parent;

    public $parentfield;

    public $upfield;

    public $has_metadata = true;

    public $subtypes = array();

    private $dbfields = array();

    /**
     *
     * @var array
     */
    protected $mixins = array();

    /**
     *
     * @var array
     */
    private $properties = array();

    public function __construct(SimpleXMLElement $attributes)
    {
        foreach ($attributes as $name => $value)
        {
            if ($name == 'metadata')
            {
                $this->has_metadata = ($value === 'true');
            }
            else
            {
                $this->$name = (string) $value;
            }
        }
    }

    public function add_property(node $property, $name = null)
    {
        if ($name === null)
        {
            $name = $property->name;
        }

        if ($property instanceof mixin)
        {
            $this->mixins[$name] = $property;
        }
        else
        {
            if (!array_key_exists($property->field, $this->dbfields))
            {
                $this->properties[$name] = $property;
                $this->dbfields[$property->field] = true;
            }
            //TODO: Can we create an alias?
        }
    }

    public function has_property($name)
    {
        return array_key_exists($name, $this->properties);
    }

    public function get_properties()
    {
        return $this->properties;
    }

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