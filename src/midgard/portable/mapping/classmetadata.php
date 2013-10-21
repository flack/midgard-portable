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
    public $midgard = array
    (
        'parent' => null,
        'parentfield' => null,
        'upfield' => null,
        'unique_fields' => array()
    );

    public function __sleep()
    {
        $serialized = parent::__sleep();
        $serialized[] = 'midgard';
        return $serialized;
    }
}