<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;
use midgard_connection;
use midgard\portable\storage\connection;

class mgdobject_no_metadataTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();

        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = $factory->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_construct()
    {
        $topic = $this->make_object('midgard_no_metadata');
        $this->assertNull($topic->metadata);
    }

    public function test_load()
    {
        $topic = $this->make_object('midgard_no_metadata');
        $topic->name = __FUNCTION__;

        $this->assert_api('create', $topic);
        self::$em->clear();

        $loaded = $this->make_object('midgard_no_metadata', $topic->id);
        $this->assertEquals($topic->id, $loaded->id);
        $this->assertNotEquals('', $loaded->guid);
        $this->assertEquals($topic->name, $loaded->name);

        $loaded2 = $this->make_object('midgard_no_metadata', $topic->guid);
        $this->assertEquals($topic->id, $loaded2->id);
        $this->assertEquals($topic->name, $loaded2->name);
    }

    public function test_create()
    {
        $initial = $this->count_results('midgard_topic');

        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;

        $this->assert_api('create', $topic);
        $this->assertFalse(empty($topic->guid), 'GUID empty');
        $this->assertEquals($initial + 1, $this->count_results('midgard_topic'));
        $this->assertGreaterThan($initial, $topic->id);

        $topic2 = $this->make_object('midgard_topic');
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__ . '-2';
        $stat = $topic2->create();
        $this->assertTrue($stat);
        $this->assert_api('create', $topic2, MGD_ERR_DUPLICATE);
        $this->assertEquals($initial + 2, $this->count_results('midgard_topic'));
        $this->assertEquals($topic->id + 1, $topic2->id);

        $topic3 = $this->make_object('midgard_topic');
        $topic3->up = $topic->id;
        $topic3->name = __FUNCTION__ . '-3';
        $stat = $topic3->create();
        $this->assertTrue($stat);
    }

    public function test_update()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        $topic2 = $this->make_object('midgard_topic');
        $topic2->create();

        $topic->name = __FUNCTION__ . 'xxx';
        $topic->up = $topic2->id;
        $stat = $topic->update();
        $this->assertTrue($stat);
        self::$em->clear();

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertEquals($topic->name, $loaded->name);
        $this->assertEquals($topic2->id, $loaded->up, 'Wrong up ID');
        $this->assertEquals('', $loaded->title);
    }

    public function test_delete()
    {
        $topic = $this->make_object('midgard_no_metadata');
        $topic->name = uniqid(__FUNCTION__);
        $this->assert_api('create', $topic);

        $this->assert_api('delete', $topic, MGD_ERR_INVALID_PROPERTY_VALUE);
    }

    public function test_undelete()
    {
        $classname = connection::get_fqcn('midgard_no_metadata');
        $con = midgard_connection::get_instance();

        $topic = $this->make_object('midgard_no_metadata');
        $topic->name = uniqid('t1' . time());
        $topic->create();

        $stat = call_user_func_array($classname . "::undelete", [$topic->guid]);
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_INVALID_PROPERTY_VALUE, $con->get_error(), $con->get_error_string());
    }

    public function test_purge()
    {
        $initial = $this->count_results('midgard_no_metadata', true);

        $topic = $this->make_object('midgard_no_metadata');
        $topic->name = __FUNCTION__;
        $topic->create();
        $id = $topic->id;
        $this->assert_api('purge', $topic);
        $this->assertEquals($initial, $this->count_results('midgard_no_metadata', true));
        $this->assert_api('purge', $topic, MGD_ERR_NOT_EXISTS);

        $topic = $this->make_object('midgard_no_metadata');
        $topic->name = __FUNCTION__ . ' 2';
        $topic->create();
        $this->assertEquals($id + 1, $topic->id);
    }

    public function test_lock()
    {
        $topic = $this->make_object('midgard_no_metadata');
        $topic->create();

        $this->assert_api('lock', $topic, MGD_ERR_NO_METADATA);
    }

    public function test_unlock()
    {
        $topic = $this->make_object('midgard_no_metadata');
        $topic->create();

        $this->assert_api('unlock', $topic, MGD_ERR_NO_METADATA);
    }

    public function test_approve()
    {
        $topic = $this->make_object('midgard_no_metadata');
        $topic->create();

        $this->assert_api('approve', $topic, MGD_ERR_NO_METADATA);
    }

    public function test_unapprove()
    {
        $topic = $this->make_object('midgard_no_metadata');
        $topic->create();

        $this->assert_api('unapprove', $topic, MGD_ERR_NO_METADATA);
    }
}
