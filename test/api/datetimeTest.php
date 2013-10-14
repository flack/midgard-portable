<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_datetime;

class datetimeTest extends testcase
{
    public function test_construct()
    {
        $date = new midgard_datetime();
        $this->assertEquals('0000-01-01 00:00:00', $date->format('Y-m-d H:i:s'));
    }
}