<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_object_class;
use midgard_connection;
use midgard\portable\api\error\exception;
use midgard\portable\storage\connection;

class classTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = [
            $factory->getMetadataFor(connection::get_fqcn('midgard_language')),
            $factory->getMetadataFor(connection::get_fqcn('midgard_topic')),
            $factory->getMetadataFor(connection::get_fqcn('midgard_article')),
            $factory->getMetadataFor(connection::get_fqcn('midgard_repligard')),
            $factory->getMetadataFor(connection::get_fqcn('midgard_no_metadata')),
        ];
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_factory()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $object = midgard_object_class::factory('midgard_topic');
        $this->assertInstanceOf($classname, $object);

        $topic = new $classname;
        $topic->create();

        $object = midgard_object_class::factory('midgard_topic', $topic->id);
        $this->assertInstanceOf($classname, $object);

        $object = midgard_object_class::factory('midgard_topic', $topic->guid);
        $this->assertInstanceOf($classname, $object);
    }

    public function test_get_object_by_guid()
    {
        $classname = connection::get_fqcn('midgard_topic');

        $topic = new $classname;
        $topic->create();

        $object = midgard_object_class::get_object_by_guid($topic->guid);
        $this->assertInstanceOf($classname, $object);

        $e = null;
        try {
            midgard_object_class::get_object_by_guid('111111111111111111111111111111111111111111111111111');
        } catch (exception $e) {
        }
        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, \midgard_connection::get_instance()->get_error());
    }

    public function test_get_object_by_guid_deleted()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $topic = new $classname;
        $this->assert_api('create', $topic);
        $this->assert_api('delete', $topic);

        $e = null;
        try {
            midgard_object_class::get_object_by_guid($topic->guid);
        } catch (exception $e) {
        }
        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_OBJECT_DELETED, \midgard_connection::get_instance()->get_error());
    }

    public function test_get_object_by_guid_purged()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $topic = new $classname;
        $this->assert_api('create', $topic);
        $this->assert_api('purge', $topic);

        $e = null;
        try {
            midgard_object_class::get_object_by_guid($topic->guid);
        } catch (exception $e) {
        }
        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_OBJECT_PURGED, \midgard_connection::get_instance()->get_error());
    }

    public function test_get_object_by_guid_invalid()
    {
        $e = null;
        try {
            midgard_object_class::get_object_by_guid('XXX');
        } catch (exception $e) {
        }
        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, \midgard_connection::get_instance()->get_error());
    }

    public function test_get_object_by_guid_no_metadata()
    {
        $classname = connection::get_fqcn('midgard_no_metadata');

        $topic = new $classname;
        $this->assert_api('create', $topic);

        $object = midgard_object_class::get_object_by_guid($topic->guid);
        $this->assertInstanceOf($classname, $object);
    }

    public function test_get_property_up()
    {
        $up = midgard_object_class::get_property_up(connection::get_fqcn('midgard_topic'));
        $this->assertEquals('up', $up);
    }

    public function test_get_property_parent()
    {
        $parentfield = midgard_object_class::get_property_parent(connection::get_fqcn('midgard_article'));
        $this->assertEquals('topic', $parentfield);

        $parentfield = midgard_object_class::get_property_parent(connection::get_fqcn('midgard_person'));
        $this->assertNull($parentfield);
    }

    public function test_undelete()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $con = midgard_connection::get_instance();

        // test undelete on invalid guid
        $stat = midgard_object_class::undelete("hello");
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, $con->get_error(), $con->get_error_string());

        // test undelete on not deleted topic
        $topic = new $classname;
        $topic->name = uniqid('t1' . time());
        $topic->create();

        $stat = midgard_object_class::undelete($topic->guid);
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_INTERNAL, $con->get_error(), $con->get_error_string());

        // test undelete on purged topic
        $topic->purge();

        $stat = midgard_object_class::undelete($topic->guid);
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_OBJECT_PURGED, $con->get_error(), $con->get_error_string());

        // test undelete that should work
        $initial = $this->count_results($classname);
        $initial_all = $this->count_results($classname, true);

        $topic = new $classname;
        $name = uniqid(__FUNCTION__);
        $topic->name = $name;
        $topic->create();

        $this->assert_api('delete', $topic);

        // after delete
        $this->assertEquals($initial, $this->count_results($classname));
        $this->assertEquals($initial_all + 1, $this->count_results($classname, true));

        $topic->name = uniqid(__FUNCTION__ . time());
        $stat = midgard_object_class::undelete($topic->guid);
        $this->assertTrue($stat, $con->get_error_string());
        $this->verify_unpersisted_changes($classname, $topic->guid, "name", $name);

        // after undelete
        $this->assertEquals($initial + 1, $this->count_results($classname));
        $this->assertEquals($initial_all + 1, $this->count_results($classname, true));
    }

    public function test_has_metadata()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $topic = new $classname;
        $u_classname = connection::get_fqcn('midgard_user');
        $user = new $u_classname;

        $this->assertTrue(midgard_object_class::has_metadata($classname));
        $this->assertTrue(midgard_object_class::has_metadata($topic));
        $this->assertFalse(midgard_object_class::has_metadata($u_classname));
        $this->assertFalse(midgard_object_class::has_metadata($user));
    }
}
