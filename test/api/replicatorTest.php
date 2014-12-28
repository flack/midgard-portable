<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use \SimpleXMLElement;
use midgard_replicator;

class midgard_replicatorTest extends testcase
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $classes = array(
            self::$em->getClassMetadata(self::$ns . '\\midgard_topic'),
            self::$em->getClassMetadata('midgard:midgard_repligard'),
            self::$em->getClassMetadata('midgard:midgard_article')
        );
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_serialize_nonpersistent()
    {
        $classname = self::$ns . '\\midgard_topic';
        $object = new $classname;
        $ret = midgard_replicator::serialize($object);
        $this->assertInternalType('string', $ret);
        $actual = new SimpleXMLElement($ret);
        $expected = new SimpleXMLElement(dirname(__DIR__) . '/__files/replicator/new_topic.xml', 0, true);
        $this->assertEquals($expected->midgard_topic->attributes(), $actual->midgard_topic->attributes());
        $this->assertEquals($expected->midgard_topic[0]->count(), $actual->midgard_topic[0]->count());
        $this->assertEquals($expected->midgard_topic->symlink, $actual->midgard_topic->symlink);
        $this->assertEquals($expected->midgard_topic->styleinherit, $actual->midgard_topic->styleinherit);
        $this->assertEquals($expected->midgard_topic->metadata->created, $actual->midgard_topic->metadata->created);
    }

    public function test_serialize()
    {
        $classname = self::$ns . '\\midgard_topic';
        $object = new $classname;
        $this->assert_api('create', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals('created', (string) $actual->midgard_topic['action']);
        $this->assertEquals($object->id, (string) $actual->midgard_topic->id);

        $this->assert_api('update', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals('updated', (string) $actual->midgard_topic['action']);

        $this->assert_api('delete', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals('deleted', (string) $actual->midgard_topic['action']);

        $this->assert_api('purge', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals('purged', (string) $actual->midgard_topic['action']);
    }

    public function test_serialize_child()
    {
        $classname = self::$ns . '\\midgard_topic';
        $parent = new $classname;
        $this->assert_api('create', $parent);

        $object = new $classname;
        $object->up = $parent->id;
        $this->assert_api('create', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals($parent->guid, (string) $actual->midgard_topic->up);
    }

    public function test_unserialize_nonpersistent()
    {
        $classname = self::$ns . '\\midgard_topic';
        $expected = new $classname;
        $ret = midgard_replicator::unserialize(file_get_contents(dirname(__DIR__) . '/__files/replicator/new_topic.xml'));

        $this->assertCount(1, $ret);
        $actual = $ret[0];
        $this->assertEquals(get_class($expected), get_class($actual));
    }

    public function test_unserialize()
    {
        $classname = self::$ns . '\\midgard_topic';
        $object = new $classname;
        $this->assert_api('create', $object);
        $ret = midgard_replicator::unserialize(midgard_replicator::serialize($object));
        $this->assertEquals($object->guid, $ret[0]->guid);
        $this->assertEquals($object->id, $ret[0]->id);
        $this->assertEquals($object->metadata->created, $ret[0]->metadata->created);
        $this->assertEquals('created', $ret[0]->action);

        $this->assert_api('update', $object);
        $ret = midgard_replicator::unserialize(midgard_replicator::serialize($object));
        $this->assertEquals($object->guid, $ret[0]->guid);
        $this->assertEquals($object->id, $ret[0]->id);
        $this->assertEquals($object->metadata->revised, $ret[0]->metadata->revised);
        $this->assertEquals('updated', $ret[0]->action);

        $this->assert_api('delete', $object);
        $ret = midgard_replicator::unserialize(midgard_replicator::serialize($object));
        $this->assertEquals($object->guid, $ret[0]->guid);
        $this->assertEquals($object->id, $ret[0]->id);
        $this->assertEquals($object->metadata->deleted, $ret[0]->metadata->deleted);
        $this->assertEquals('deleted', $ret[0]->action);

        $this->assert_api('purge', $object);
        $ret = midgard_replicator::unserialize(midgard_replicator::serialize($object));
        $this->assertEquals($object->guid, $ret[0]->guid);
        $this->assertEquals($object->id, $ret[0]->id);
        $this->assertEquals('purged', $ret[0]->action);
    }

    public function test_unserialize_child()
    {
        $classname = self::$ns . '\\midgard_topic';
        $parent = new $classname;
        $this->assert_api('create', $parent);

        $object = new $classname;
        $object->up = $parent->id;
        $this->assert_api('create', $object);
        $ret = midgard_replicator::unserialize(midgard_replicator::serialize($object));
        $this->assertEquals($parent->id, $ret[0]->up);
    }

    public function test_export_by_guid()
    {
        $classname = self::$ns . '\\midgard_topic';

        $object = new $classname;
        $this->assertFalse(midgard_replicator::export_by_guid($object->guid));
        $this->assert_error(MGD_ERR_INVALID_PROPERTY_VALUE);

        $this->assert_api('create', $object);
        $this->assertTrue(midgard_replicator::export_by_guid($object->guid));
        $refreshed = new $classname($object->id);
        $this->assertNotEquals((string) $refreshed->metadata->exported, (string) $object->metadata->exported);

        $this->assert_api('purge', $object);
        $this->assertFalse(midgard_replicator::export_by_guid($object->guid));
        $this->assert_error(MGD_ERR_OBJECT_PURGED);
    }
}