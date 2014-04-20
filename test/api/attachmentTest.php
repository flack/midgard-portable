<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\api\blob;
use midgard_connection;

class attachmentTest extends testcase
{
    public static function setupBeforeClass()
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
        $classname = self::$ns . '\\midgard_attachment';
        $att = new $classname;

        $this->assertFalse($att->create());

        $t_classname = self::$ns . '\\midgard_topic';
        $topic = new $t_classname;
        $topic->create();

        $att->parentguid = $topic->guid;
        $this->assertTrue($att->create(), midgard_connection::get_instance()->get_error_string());
    }

    public function test_has_attachments()
    {
        $t_classname = self::$ns . '\\midgard_topic';
        $topic = new $t_classname;
        $topic->create();

        $this->assertFalse($topic->has_attachments());

        $classname = self::$ns . '\\midgard_attachment';
        $att = new $classname;
        $att->parentguid = $topic->guid;
        $att->create();

        $this->assertTrue($topic->has_attachments());
    }

    public function test_list_attachments()
    {
        $t_classname = self::$ns . '\\midgard_topic';
        $topic = new $t_classname;
        $topic->create();

        $this->assertEquals(array(), $topic->list_attachments());

        $classname = self::$ns . '\\midgard_attachment';
        $att = new $classname;
        $att->parentguid = $topic->guid;
        $att->create();

        $attachments = $topic->list_attachments();
        $this->assertCount(1, $attachments);
        $this->assertEquals($attachments[0]->guid, $att->guid);
    }

    public function test_create_attachment()
    {
        $t_classname = self::$ns . '\\midgard_topic';
        $topic = new $t_classname;
        $topic->create();

        $this->assertEquals(array(), $topic->list_attachments());
        $att = $topic->create_attachment('name');
        $this->assertEquals(MGD_ERR_OK, midgard_connection::get_instance()->get_error());
        $this->assertInstanceOf(self::$ns . '\\midgard_attachment', $att);

        $att = $topic->create_attachment('name');
        $this->assertEquals(MGD_ERR_OBJECT_NAME_EXISTS, midgard_connection::get_instance()->get_error());
        $this->assertNull($att);

        $attachments = $topic->list_attachments();
        $this->assertCount(1, $attachments);
    }
}