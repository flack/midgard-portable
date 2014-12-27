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

    public function test_serialize_nonpersistent_topic()
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

    public function test_serialize_created_topic()
    {
        $classname = self::$ns . '\\midgard_topic';
        $object = new $classname;
        $this->assert_api('create', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals('created', (string) $actual->midgard_topic['action']);
    }

    public function test_serialize_updated_topic()
    {
        $classname = self::$ns . '\\midgard_topic';
        $object = new $classname;
        $this->assert_api('create', $object);
        $this->assert_api('update', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals('updated', (string) $actual->midgard_topic['action']);
    }

    public function test_serialize_deleted_topic()
    {
        $classname = self::$ns . '\\midgard_topic';
        $object = new $classname;
        $this->assert_api('create', $object);
        $this->assert_api('delete', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals('deleted', (string) $actual->midgard_topic['action']);
    }

    public function test_serialize_purged_topic()
    {
        $classname = self::$ns . '\\midgard_topic';
        $object = new $classname;
        $this->assert_api('create', $object);
        $this->assert_api('delete', $object);
        $this->assert_api('purge', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals('purged', (string) $actual->midgard_topic['action']);
    }

    public function test_serialize_child_topic()
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
}