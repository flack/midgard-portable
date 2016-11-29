<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\mapping;

use Doctrine\ORM\Mapping\ClassMetadataFactory;

class factory extends ClassMetadataFactory
{
    protected function newClassMetadataInstance($className)
    {
        return new classmetadata($className);
    }
}
