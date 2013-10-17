<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api;

use midgard_metadata;

class metadata extends midgard_metadata
{
    private $object;

    public function __construct($object)
    {
        $this->object = $object;
    }

    public function __get($property)
    {
        return $this->object->{'metadata_' . $property};
    }

    public function __set($property, $value)
    {
    }
}
?>
