<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

class midgard_datetime extends DateTime
{
    public function __construct($time = 'now', $timezone = null)
    {
        parent::__construct($time, $timezone ?? new DateTimeZone('UTC'));
    }

    public function __toString()
    {
        return $this->format('c');
    }
}
