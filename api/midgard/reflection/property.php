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
    public function property_exists($property, $metadata = false)
    {
        if ($metadata) {
            $property = 'metadata_' . $property;
        }
        return ($this->cm->hasField($property) || $this->cm->hasAssociation($property) || array_key_exists($property, $this->cm->midgard['field_aliases']));
    }

    /**
     * Returns field's description, if any
     *
     * @param string $property
     * @return string|NULL
     */
    public function description($property)
    {
        if (!$this->cm->hasField($property)) {
            return null;
        }
        $mapping = $this->cm->getFieldMapping($property);
        return $mapping['midgard:description'];
    }

    public function get_mapping($property)
    {
        if (!$this->cm->hasField($property)) {
            return null;
        }
        return $this->cm->getFieldMapping($property);
    }

    /**
     * Is this field a link or not
     *
     * @param string $property
     * @return boolean
     */
    public function is_link($property)
    {
        if ($this->cm->hasAssociation($property)) {
            return true;
        }
        return $this->is_special_link($property);
    }

    public function is_special_link($property)
    {
        $mapping = $this->get_mapping($property);
        if ($this->cm->hasAssociation($property) || is_null($mapping)) {
            return false;
        }
        return isset($mapping["noidlink"]);
    }

    /**
     * Returns the classname for the link target
     *
     * @param string $property
     * @return string|NULL
     */
    public function get_link_name($property)
    {
        if ($this->cm->hasAssociation($property)) {
            $mapping = $this->cm->getAssociationMapping($property);
            return $mapping['midgard:link_name'];
        }
        $mapping = $this->get_mapping($property);
        if (is_null($mapping)) {
            return null;
        }
        if (isset($mapping["noidlink"]["target"])) {
            return $mapping["noidlink"]["target"];
        }
        return null;
    }

    /**
     * Returns the target field name
     *
     * @param string $property
     * @return string|NULL
     */
    public function get_link_target($property)
    {
        if ($this->cm->hasAssociation($property)) {
            $mapping = $this->cm->getAssociationMapping($property);
            return $mapping['midgard:link_target'];
        }
        $mapping = $this->get_mapping($property);
        if (is_null($mapping)) {
            return null;
        }
        if (isset($mapping["noidlink"]["field"])) {
            return $mapping["noidlink"]["field"];
        }
        return null;
    }

    /**
     * Returns field type constant
     *
     * @param string $property
     * @return integer
     */
    public function get_midgard_type($property)
    {
        if ($this->cm->hasField($property)) {
            $mapping = $this->cm->getFieldMapping($property);
            return $mapping['midgard:midgard_type'];
        } elseif ($this->cm->hasAssociation($property)) {
            // for now, only PK fields are supported, which are always IDs, so..
            return translator::TYPE_UINT;
        }
        return translator::TYPE_NONE;
    }
}
