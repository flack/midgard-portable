<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\connection;
use midgard\portable\mgdschema\translator;

class midgard_reflection_property
{
    /**
     *
     * @var Doctrine\Common\Persistence\Mapping\ClassMetadata
     */
    private $cm;

    public function __construct($mgdschema_class)
    {
        $this->cm = connection::get_em()->getClassMetadata('midgard:' . $mgdschema_class);
    }

    public function description($property)
    {
        throw new \exception('not implemented yet');
    }

    public function is_link($property)
    {
        return $this->cm->hasAssociation($property);
    }

    public function get_link_name($property)
    {
        if (!$this->cm->hasAssociation($property))
        {
            return null;
        }
        $mapping = $this->cm->getAssociationMapping($property);
        return $mapping['midgard:link_name'];
    }

    public function get_link_target($property)
    {
        if (!$this->cm->hasAssociation($property))
        {
            return null;
        }
        $mapping = $this->cm->getAssociationMapping($property);
        return $mapping['midgard:link_target'];
    }

    public function get_midgard_type($property)
    {
        if ($this->cm->hasField($property))
        {
            $mapping = $this->cm->getFieldMapping($property);
            return $mapping['midgard:midgard_type'];
        }
        else if ($this->cm->hasAssociation($property))
        {
            // for now, only PK fields are supported, which are always IDs, so..
            return translator::TYPE_UINT;
        }
        return translator::TYPE_NONE;
    }
}