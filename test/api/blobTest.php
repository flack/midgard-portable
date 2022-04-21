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
    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = $factory->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        midgard_connection::get_instance()->config->create_blobdir();
    }

    public function test_construct()
    {
        $att = $this->make_object('midgard_attachment');

        $blob = new blob($att);
        $this->assertEquals('', $blob->content);
    }

    public function test_get_handler()
    {
        $att = $this->make_object('midgard_attachment');

        $blob = new blob($att);
        $handle = $blob->get_handler();
        $this->assertIsResource($handle);
        $metadata = stream_get_meta_data($handle);
        $this->assertEquals('w', $metadata['mode']);
    }

    public function test_get_path()
    {
        $topic = $this->make_object('midgard_topic');
        $this->assert_api('create', $topic);

        $att = $this->make_object('midgard_attachment');
        $att->location = '1/A/1ad4ec493ba13c329049de5d60ac8193';
        $att->name = uniqid();
        $att->parentguid = $topic->guid;
        $this->assert_api('create', $att);
        $blob = new blob($att);
        $prefix = midgard_connection::get_instance()->config->blobdir;

        $this->assertSame($prefix . '/1/A/1ad4ec493ba13c329049de5d60ac8193', $blob->get_path());

        $att = $this->make_object('midgard_attachment');
        $att->name = uniqid();
        $att->parentguid = $topic->guid;
        $this->assert_api('create', $att);
        $blob = new blob($att);
        $prefix = midgard_connection::get_instance()->config->blobdir;

        $this->assertNotEquals($prefix . '/', $blob->get_path());
        $this->assertFileExists(dirname($blob->get_path()));
        $this->assertFileNotExists($blob->get_path());
        $this->assertEquals($blob->get_path(), $prefix . '/' . $att->location);
    }

    public function test_exists()
    {
        $blob = new blob($this->make_object('midgard_attachment'));
        $this->assertFalse($blob->exists());
        $handle = $blob->get_handler();
        $this->assertIsResource($handle);
        $this->assertTrue($blob->exists());
    }

    public function test_read_content()
    {
        $blob = new blob($this->make_object('midgard_attachment'));
        $this->assertNull($blob->read_content());
        $handle = $blob->get_handler();
        $this->assertIsResource($handle);
        $this->assertSame('', $blob->read_content());
    }

    public function test_write_content()
    {
        $blob = new blob($this->make_object('midgard_attachment'));
        $this->assertNull($blob->read_content());
        $this->assertTrue($blob->write_content('X'));
        $this->assertSame('X', $blob->read_content());
    }
}
