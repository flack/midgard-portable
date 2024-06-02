<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\mapping;

use Doctrine\ORM\Mapping\ClassMetadata as base_metadata;

class classmetadata extends base_metadata
{
    public array $midgard = [
        'parent' => null,
        'parentfield' => null,
        'upfield' => null,
        'unique_fields' => [],
        'childtypes' => [],
        'field_aliases' => [],
        'field_order' => [],
        'links_as_entities' => false
    ];

    public function __sleep()
    {
        $serialized = parent::__sleep();
        $serialized[] = 'midgard';
        return $serialized;
    }

    /**
     * @param boolean $metadata Return metadata properties instead
     * @return string[]
     */
    public function get_schema_properties(bool $metadata = false) : array
    {
        if ($metadata === true) {
            $metadata = 0;
        }
        $properties = array_filter($this->midgard['field_order'], function($input) use ($metadata) {
            if (strpos($input, 'metadata_') === $metadata) {
                return $input;
            }
        });
        if ($metadata === false) {
            $properties[] = 'metadata';
        } else {
            $properties = array_map(function($input) {
                return str_replace('metadata_', '', $input);
            }, $properties);
        }
        return $properties;
    }
}
