<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;
use midgard\portable\storage\connection;
use midgard_connection;

class object_no_metadataTest extends testcase
{
    public static function setupBeforeClass()
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
        $classname = self::$ns . '\\midgard_no_metadata';
        $topic = new $classname;
        $this->assertNull($topic->metadata);
    }

    public function test_load()
    {
        $classname = self::$ns . '\\midgard_no_metadata';
        $topic = new $classname;
        $topic->name = __FUNCTION__;

        $this->assert_api('create', $topic);
        self::$em->clear();

        $loaded = new $classname($topic->id);
        $this->assertEquals($topic->id, $loaded->id);
        $this->assertNotEquals('', $loaded->guid);
        $this->assertEquals($topic->name, $loaded->name);

        $loaded2 = new $classname($topic->guid);
        $this->assertEquals($topic->id, $loaded2->id);
        $this->assertEquals($topic->name, $loaded2->name);
    }

    public function test_create()
    {
        $classname = self::$ns . '\\midgard_topic';
        $initial = $this->count_results($classname);

        $topic = new $classname;
        $topic->name = __FUNCTION__;

        $this->assert_api('create', $topic);
        $this->assertFalse(empty($topic->guid), 'GUID empty');
        $this->assertEquals($initial + 1, $this->count_results($classname));
        $this->assertGreaterThan($initial, $topic->id);

        $topic2 = new $classname;
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__ . '-2';
        $stat = $topic2->create();
        $this->assertTrue($stat);
        $this->assert_api('create', $topic2, MGD_ERR_DUPLICATE);
        $this->assertEquals($initial + 2, $this->count_results($classname));
        $this->assertEquals($topic->id + 1, $topic2->id);

        $topic3 = new $classname;
        $topic3->up = $topic->id;
        $topic3->name = __FUNCTION__ . '-3';
        $stat = $topic3->create();
        $this->assertTrue($stat);
    }

    public function test_update()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->create();
        $topic2 = new $classname;
        $topic2->create();

        $topic->name = __FUNCTION__ . 'xxx';
        $topic->up = $topic2->id;
        $stat = $topic->update();
        $this->assertTrue($stat);
        self::$em->clear();

        $loaded = new $classname($topic->id);
        $this->assertEquals($topic->name, $loaded->name);
        $this->assertEquals($topic2->id, $loaded->up, 'Wrong up ID');
        $this->assertEquals('', $loaded->title);
    }

    public function test_delete()
    {
        $classname = self::$ns . '\\midgard_no_metadata';
        $topic = new $classname;
        $topic->name = uniqid(__FUNCTION__);
        $this->assert_api('create', $topic);

        $this->assert_api('delete', $topic, MGD_ERR_INVALID_PROPERTY_VALUE);
    }

    public function test_undelete()
    {
        $classname = self::$ns . '\\midgard_no_metadata';
        $con = midgard_connection::get_instance();

        $topic = new $classname;
        $topic->name = uniqid('t1' . time());
        $topic->create();

        $stat = call_user_func_array($classname . "::undelete", [$topic->guid]);
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_INVALID_PROPERTY_VALUE, $con->get_error(), $con->get_error_string());
    }

    public function test_purge()
    {
        $classname = self::$ns . '\\midgard_no_metadata';
        $initial = $this->count_results($classname, true);

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        $id = $topic->id;
        $this->assert_api('purge', $topic);
        $this->assertEquals($initial, $this->count_results($classname, true));
        $this->assert_api('purge', $topic, MGD_ERR_NOT_EXISTS);

        $topic = new $classname;
        $topic->name = __FUNCTION__ . ' 2';
        $topic->create();
        $this->assertEquals($id + 1, $topic->id);
    }

    public function test_lock()
    {
        $classname = self::$ns . '\\midgard_no_metadata';
        $topic = new $classname;
        $topic->create();

        $this->assert_api('lock', $topic, MGD_ERR_NO_METADATA);
    }

    public function test_unlock()
    {
        $classname = self::$ns . '\\midgard_no_metadata';
        $topic = new $classname;
        $topic->create();

        $this->assert_api('unlock', $topic, MGD_ERR_NO_METADATA);
    }

    public function test_approve()
    {
        $classname = self::$ns . '\\midgard_no_metadata';
        $topic = new $classname;
        $topic->create();

        $this->assert_api('approve', $topic, MGD_ERR_NO_METADATA);
    }

    public function test_unapprove()
    {
        $classname = self::$ns . '\\midgard_no_metadata';
        $topic = new $classname;
        $topic->create();

        $this->assert_api('unapprove', $topic, MGD_ERR_NO_METADATA);
    }
}
