<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use Doctrine\Common\Util\ClassUtils;
use midgard\portable\storage\connection;
use midgard\portable\mgdschema\translator;

class midgard_reflection_property
{
    /**
     * @var midgard\portable\mapping\classmetadata
     */
    private $cm;

    public function __construct($mgdschema_class)
    {
        // we might get a proxy class, so we need to translate
        $classname = ClassUtils::getRealClass($mgdschema_class);
        $cmf = connection::get_em()->getMetadataFactory();
        if (!$cmf->hasMetadataFor($classname)) {
            $classname = 'midgard:' . $mgdschema_class;
        }
        $this->cm = $cmf->getMetadataFor($classname);
    }

    /**
     * @param string $property The property name
     * @param boolean $metadata Check metadata properties instead
     * @return boolean Indicating existence
     */
    public function property_exists(string $property, bool $metadata = false) : bool
    {
        if ($metadata) {
            $property = 'metadata_' . $property;
        }
        return $this->cm->hasField($property) || $this->cm->hasAssociation($property) || array_key_exists($property, $this->cm->midgard['field_aliases']);
    }

    /**
     * Returns field's description, if any
     */
    public function description(string $property) : ?string
    {
        return $this->get_mapping($property)['midgard:description'] ?? null;
    }

    private function get_mapping(string $property) : ?array
    {
        if (!$this->cm->hasField($property)) {
            return null;
        }
        return $this->cm->getFieldMapping($property);
    }

    /**
     * Is this field a link or not
     */
    public function is_link(string $property) : bool
    {
        return $this->cm->hasAssociation($property) || $this->is_special_link($property);
    }

    public function is_special_link(string $property) : bool
    {
        if ($this->cm->hasAssociation($property)) {
            return false;
        }
        return !empty($this->get_mapping($property)["noidlink"]);
    }

    /**
     * Returns the classname for the link target
     */
    public function get_link_name(string $property) : ?string
    {
        if ($this->cm->hasAssociation($property)) {
            $mapping = $this->cm->getAssociationMapping($property);
            return $mapping['midgard:link_name'];
        }

        return $this->get_mapping($property)["noidlink"]["target"] ?? null;
    }

    /**
     * Returns the target field name
     */
    public function get_link_target(string $property) : ?string
    {
        if ($this->cm->hasAssociation($property)) {
            $mapping = $this->cm->getAssociationMapping($property);
            return $mapping['midgard:link_target'];
        }

        return $this->get_mapping($property)["noidlink"]["field"] ?? null;
    }

    /**
     * Returns field type constant
     */
    public function get_midgard_type(string $property) : int
    {
        if ($this->cm->hasField($property)) {
            return $this->get_mapping($property)['midgard:midgard_type'];
        }
        if ($this->cm->hasAssociation($property)) {
            // for now, only PK fields are supported, which are always IDs, so..
            return translator::TYPE_UINT;
        }
        return translator::TYPE_NONE;
    }
}
