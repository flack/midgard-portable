<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\factory;

class midgard_reflection_property
{
    /**
     *
     * @var type
     */
    private $type;

    public function __construct($mgdschema_class)
    {
        $this->type = factory::get_type($mgdschema_class);
    }

    public function description($property)
    {
        return $this->type->get_property($property)->description;
    }

    public function is_link($property)
    {
        return $this->type->get_property($property)->is_link;
    }

    public function get_link_name($property)
    {
        return $this->type->get_property($property)->link_name;
    }

    public function get_link_target($property)
    {
        return $this->type->get_property($property)->link_target;
    }

    public function get_midgard_type($property)
    {
        return $this->type->get_property($property)->midgard_type;
    }
}