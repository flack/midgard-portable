<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_datetime;

class midgard_datetimeTest extends testcase
{
    public function test_construct()
    {
        $date = new midgard_datetime;
        $date->setDate(2009, 06, 10);
        $date = new midgard_datetime($date);
        $this->assertEquals('2009-06-10', $date->format("Y-m-d"));
    }

    public function test_midgard_001()
    {
        $date = new midgard_datetime();
        $date->setDate(2009, 06, 10);
        $this->assertEquals('2009-06-10', $date->format("Y-m-d"));
        $date->setTime(12, 41);
        $this->assertEquals('2009-06-10 12:41:00', $date->format("Y-m-d H:i:s"));
        $date->setTime(12, 41, 10);
        $this->assertEquals('2009-06-10 12:41:10', $date->format("Y-m-d H:i:s"));
        $date->setISODate(2009, 05, 3);
        $this->assertEquals('2009-01-28 12:41:10', $date->format("Y-m-d H:i:s"));
        $date->modify('+1 day');
        $this->assertEquals('2009-01-29 12:41:10', $date->format("Y-m-d H:i:s"));
        $this->assertEquals('2009-01-29T12:41:10+00:00', (string) $date);
    }
}