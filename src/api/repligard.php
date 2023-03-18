<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\api;

use midgard\portable\storage\subscriber;

/**
 * @property string $guid
 * @property string $typename
 * @property integer $object_action
 */
class repligard extends dbobject
{
    protected int $id = 0;

    protected $typename = '';

    protected $object_action = subscriber::ACTION_NONE;
}
