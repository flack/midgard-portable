<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

class midgard_datetime extends DateTime
{
    public function __construct($time = null, $object = null)
    {
        if ($time === null)
        {
            $time = '0000-01-01 00:00:00';
        }
        parent::__construct($time, $object);
    }
}