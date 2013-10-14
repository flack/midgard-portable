<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\subscriber;

class midgard_repligard extends midgard_dbobject
{
    protected $id = 0;

    protected $typename = '';

    protected $object_action = subscriber::ACTION_NONE;
}