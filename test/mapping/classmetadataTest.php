<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test\mapping;

use \midgard\portable\test\testcase;
use midgard\portable\storage\connection;

class classmetadataTest extends testcase
{
    public function test_get_schema_properties()
    {
        $cm = self::$em->getClassMetadata(connection::get_fqcn('midgard_user'));
        $props = $cm->get_schema_properties();
        $expected = ['id', 'login', 'password', 'active', 'authtype', 'authtypeid', 'usertype', 'person', 'guid', 'metadata'];
        $this->assertEquals($expected, $props);

        $props = $cm->get_schema_properties(true);
        $this->assertEquals([], $props);
    }
}
