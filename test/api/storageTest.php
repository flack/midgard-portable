<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_storage;
use midgard_query_builder;

class midgard_storageTest extends testcase
{
    public function test_create_base_storage()
    {
        $stat = midgard_storage::create_base_storage();
        $this->assertTrue($stat);
        $cm = self::$em->getMetadataFactory()->getMetadataFor('midgard:midgard_user');
        $this->assertInstanceOf('midgard\portable\mapping\classmetadata', $cm);

        $fqcn = $cm->fullyQualifiedClassName('midgard_user');
        $tokens = array
        (
            'authtype' => 'Plaintext',
            'login' => 'admin',
            'password' => 'password',
        );
        $admin = new $fqcn($tokens);
        $this->assertEquals(2, $admin->usertype);
        $this->assertTrue(midgard_storage::create_base_storage());
    }
}