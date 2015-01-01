<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_object_class;

class classTest extends testcase
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = array(
            $factory->getMetadataFor('midgard:midgard_language'),
            $factory->getMetadataFor('midgard:midgard_topic'),
            $factory->getMetadataFor('midgard:midgard_article'),
            $factory->getMetadataFor('midgard:midgard_repligard'),
        );
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_factory()
    {
        $classname = self::$ns . '\\midgard_topic';
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
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->create();

        $object = midgard_object_class::get_object_by_guid($topic->guid);
        $this->assertInstanceOf($classname, $object);

        $e = null;
        try
        {
            $object = midgard_object_class::get_object_by_guid('111111111111111111111111111111111111111111111111111');
        }
        catch (\midgard_error_exception $e)
        {
        }
        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, \midgard_connection::get_instance()->get_error());
    }

    public function test_get_object_by_guid_deleted()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $this->assert_api('create', $topic);
        $this->assert_api('delete', $topic);

        $e = null;
        try
        {
            $object = midgard_object_class::get_object_by_guid($topic->guid);
        }
        catch (\midgard_error_exception $e)
        {
        }
        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_OBJECT_DELETED, \midgard_connection::get_instance()->get_error());
    }

    public function test_get_object_by_guid_purged()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $this->assert_api('create', $topic);
        $this->assert_api('purge', $topic);

        $e = null;
        try
        {
            $object = midgard_object_class::get_object_by_guid($topic->guid);
        }
        catch (\midgard_error_exception $e)
        {
        }
        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_OBJECT_PURGED, \midgard_connection::get_instance()->get_error());
    }

    public function test_get_object_by_guid_invalid()
    {
        $e = null;
        try
        {
            $object = midgard_object_class::get_object_by_guid('XXX');
        }
        catch (\midgard_error_exception $e)
        {
        }
        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, \midgard_connection::get_instance()->get_error());
    }

    public function test_get_property_up()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;

        $up = midgard_object_class::get_property_up($topic);
        $this->assertEquals('up', $up);
    }

    public function test_get_property_parent()
    {
        $classname = self::$ns . '\\midgard_article';
        $article = new $classname;

        $parentfield = midgard_object_class::get_property_parent($article);
        $this->assertEquals('topic', $parentfield);
    }

    public function test_undelete()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $this->assert_api('create', $topic);
        $this->assert_api('delete', $topic);

        $this->assertTrue(midgard_object_class::undelete($topic->guid));
        $refreshed = new $classname($topic->id);
        $this->assertFalse($refreshed->metadata->deleted);
    }
}