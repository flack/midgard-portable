<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\api\blob;
use midgard_connection;

class blobTest extends testcase
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        midgard_connection::get_instance()->config->create_blobdir();
    }

    public function test_construct()
    {
        $classname = self::$ns . '\\midgard_attachment';
        $att = new $classname;

        $blob = new blob($att);
        $this->assertEquals('', $blob->content);
    }

    public function test_get_handler()
    {
        $classname = self::$ns . '\\midgard_attachment';
        $att = new $classname;

        $blob = new blob($att);
        $handle = $blob->get_handler();
        $this->assertInternalType('resource', $handle);
        $metadata = stream_get_meta_data($handle);
        $this->assertEquals('w', $metadata['mode']);
    }

    public function test_exists()
    {
        $classname = self::$ns . '\\midgard_attachment';
        $att = new $classname;

        $blob = new blob($att);
        $this->assertFalse($blob->exists());
        $handle = $blob->get_handler();
        $this->assertTrue($blob->exists());
    }

    public function test_read_content()
    {
        $classname = self::$ns . '\\midgard_attachment';
        $att = new $classname;

        $blob = new blob($att);
        $this->assertNull($blob->read_content());
        $handle = $blob->get_handler();
        $this->assertSame('', $blob->read_content());
    }
}