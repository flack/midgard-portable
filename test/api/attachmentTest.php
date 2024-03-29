<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_connection;
use midgard\portable\storage\connection;

class attachmentTest extends testcase
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

    public function test_create()
    {
        $att = $this->make_object('midgard_attachment');

        $this->assertFalse($att->create());

        $topic = $this->make_object('midgard_topic');
        $topic->create();

        $att->parentguid = $topic->guid;
        $this->assertTrue($att->create(), midgard_connection::get_instance()->get_error_string());
    }

    public function test_has_attachments()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();

        $this->assertFalse($topic->has_attachments());

        $att = $this->make_object('midgard_attachment');
        $att->parentguid = $topic->guid;
        $att->create();

        $this->assertTrue($topic->has_attachments());
    }

    public function test_list_attachments()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();

        $this->assertEquals([], $topic->list_attachments());

        $att = $this->make_object('midgard_attachment');
        $att->parentguid = $topic->guid;
        $att->create();

        $attachments = $topic->list_attachments();
        $this->assertCount(1, $attachments);
        $this->assertEquals($attachments[0]->guid, $att->guid);
    }

    public function test_create_attachment()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();

        $this->assertEquals([], $topic->list_attachments());
        $att = $topic->create_attachment('name');
        $this->assertEquals(MGD_ERR_OK, midgard_connection::get_instance()->get_error());
        $this->assertInstanceOf(connection::get_fqcn('midgard_attachment'), $att);

        $att = $topic->create_attachment('name');
        $this->assertEquals(MGD_ERR_OBJECT_NAME_EXISTS, midgard_connection::get_instance()->get_error());
        $this->assertNull($att);

        $attachments = $topic->list_attachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('', $attachments[0]->location);
    }
}
