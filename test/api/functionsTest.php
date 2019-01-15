<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

class functionsTest extends testcase
{
    public function test_mgd_is_guid()
    {
        $this->assertFalse(mgd_is_guid(123));
        $this->assertFalse(mgd_is_guid(null));
        $this->assertTrue(mgd_is_guid('f0000000000000000000000000000000000f'));
        $this->assertFalse(mgd_is_guid('hi'));
        $this->assertFalse(mgd_is_guid('xf0000000000000000000000000000000000f'));
        $this->assertTrue(mgd_is_guid('f0000000000000000000000000000000000ff0000000000000000000000000000000000f'));
    }
}
