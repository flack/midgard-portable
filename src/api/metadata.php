<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api;

use midgard_datetime;

class metadata
{
    private $object;

    public function __construct($object)
    {
        $this->object = $object;
    }

    public function __get($property)
    {
        $value = $this->object->{'metadata_' . $property};
        if (   $value instanceof midgard_datetime
            && $value->format('U') == -62169984000)
        {
            //This is mainly needed for working with converted Legacy databases. Midgard2 somehow handles this internally
            //@todo Find a nicer solution and research how QB handles this
            $value->setDate(1, 1, 1);
        }
        return $value;
    }

    public function __set($property, $value)
    {
        //TODO: filter out readonly properties (?)
        $this->object->{'metadata_' . $property} = $value;
    }

    public function __isset($field)
    {
        return property_exists($this->object, 'metadata_' . $field);
    }
}
