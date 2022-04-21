<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use \SimpleXMLElement;
use midgard_replicator;
use midgard\portable\api\blob;
use midgard\portable\storage\connection;

class midgard_replicatorTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();

        $classes = self::get_metadata([
            'midgard_topic',
            'midgard_repligard',
            'midgard_article',
            'midgard_attachment',
        ]);
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_serialize_nonpersistent()
    {
        $object = $this->make_object('midgard_topic');
        $ret = midgard_replicator::serialize($object);
        $this->assertIsString($ret);
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
        $object = $this->make_object('midgard_topic');;
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
        $parent = $this->make_object('midgard_topic');
        $this->assert_api('create', $parent);

        $object = $this->make_object('midgard_topic');
        $object->up = $parent->id;
        $this->assert_api('create', $object);
        $ret = midgard_replicator::serialize($object);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals($parent->guid, (string) $actual->midgard_topic->up);
    }

    public function test_serialize_blob()
    {
        $object = $this->make_object('midgard_topic');
        $this->assert_api('create', $object);
        $att = $object->create_attachment('test', 'test');
        $blob = new blob($att);
        $blob->write_content('X');

        $ret = midgard_replicator::serialize_blob($att);
        $actual = new SimpleXMLElement($ret);
        $this->assertEquals($att->guid, (string) $actual->midgard_blob['guid']);
        $this->assertEquals('WA==', (string) $actual->midgard_blob);
    }

    public function test_unserialize_nonpersistent()
    {
        $expected = $this->make_object('midgard_topic');
        $ret = midgard_replicator::unserialize(file_get_contents(dirname(__DIR__) . '/__files/replicator/new_topic.xml'));

        $this->assertCount(1, $ret);
        $actual = $ret[0];
        $this->assertEquals(get_class($expected), get_class($actual));
    }

    public function test_unserialize()
    {
        $object = $this->make_object('midgard_topic');
        $this->assert_api('create', $object);
        $ret = midgard_replicator::unserialize(midgard_replicator::serialize($object));
        $this->assertEquals($object->guid, $ret[0]->guid);
        $this->assertEquals($object->id, $ret[0]->id);
        $this->assertEquals($object->metadata->created->format('Y-m-d H:i:s'), $ret[0]->metadata->created->format('Y-m-d H:i:s'));
        $this->assertEquals('created', $ret[0]->action);

        $this->assert_api('update', $object);
        $ret = midgard_replicator::unserialize(midgard_replicator::serialize($object));
        $this->assertEquals($object->guid, $ret[0]->guid);
        $this->assertEquals($object->id, $ret[0]->id);
        $this->assertEquals($object->metadata->revised->format('Y-m-d H:i:s'), $ret[0]->metadata->revised->format('Y-m-d H:i:s'));
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
        $parent = $this->make_object('midgard_topic');
        $this->assert_api('create', $parent);

        $object = $this->make_object('midgard_topic');
        $object->up = $parent->id;
        $this->assert_api('create', $object);
        $ret = midgard_replicator::unserialize(midgard_replicator::serialize($object));
        $this->assertEquals($parent->id, $ret[0]->up);
    }

    public function test_unserialize_blob()
    {
        $object = $this->make_object('midgard_topic');
        $this->assert_api('create', $object);
        $att = $object->create_attachment('test', 'test');
        $blob = new blob($att);
        $blob->write_content('X');

        $ret = midgard_replicator::unserialize(midgard_replicator::serialize_blob($att));
        $this->assertCount(1, $ret);
        $this->assertInstanceOf('midgard\\portable\\api\\blob', $ret[0]);
        $this->assertSame('X', $ret[0]->content);
    }

    public function test_export_by_guid()
    {
        $object = $this->make_object('midgard_topic');

        $this->assertFalse(midgard_replicator::export_by_guid($object->guid));
        $this->assert_error(MGD_ERR_INVALID_PROPERTY_VALUE);

        $this->assert_api('create', $object);
        $this->assertTrue(midgard_replicator::export_by_guid($object->guid));
        $refreshed = $this->make_object('midgard_topic', $object->id);
        $this->assertNotEquals((string) $refreshed->metadata->exported, (string) $object->metadata->exported);

        $this->assert_api('purge', $object);
        $this->assertFalse(midgard_replicator::export_by_guid($object->guid));
        $this->assert_error(MGD_ERR_OBJECT_PURGED);
    }

    public function test_import_object()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $object = $this->make_object('midgard_topic');

        $this->assertFalse(midgard_replicator::import_object($object));
        $this->assert_error(MGD_ERR_INVALID_PROPERTY_VALUE);

        $this->assert_api('create', $object);
        $object->title = 'test';
        $this->assertFalse(midgard_replicator::import_object($object));
        $this->assert_error(MGD_ERR_OBJECT_IMPORTED);

        $object->metadata->revised = new \midgard_datetime('now - 1 day');
        $this->assertFalse(midgard_replicator::import_object($object));
        $this->assert_error(MGD_ERR_OBJECT_IMPORTED);

        $object->metadata->revised = new \midgard_datetime('now + 1 day');
        $this->assertTrue(midgard_replicator::import_object($object));

        $refreshed = $this->make_object('midgard_topic', $object->id);
        $this->assertSame('test', $refreshed->title);
        $this->assertNotEquals((string) $refreshed->metadata->imported, (string) $object->metadata->imported);

        $this->assert_api('delete', $object);
        $object->metadata->deleted = false;
        $object->metadata->revised = new \midgard_datetime('now + 2 days');
        $this->assertTrue(midgard_replicator::import_object($object), \midgard_connection::get_instance()->get_error_string());

        $refreshed = $this->make_object('midgard_topic', $object->id);
        $this->assertSame('test', $refreshed->title);
        $this->assertFalse($refreshed->metadata->deleted);

        $object->metadata->deleted = true;
        $object->metadata->revised = new \midgard_datetime('now + 3 days');
        $this->assertTrue(midgard_replicator::import_object($object), \midgard_connection::get_instance()->get_error_string());

        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('guid', '=', $object->guid);
        $results = $qb->execute();
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->metadata->deleted);
    }

    public function test_import_object_purged()
    {
        $object = $this->make_object('midgard_topic');
        $this->assert_api('create', $object);
        $this->assert_api('purge', $object);
        $this->assertFalse(midgard_replicator::import_object($object), \midgard_connection::get_instance()->get_error_string());
        $this->assertTrue(midgard_replicator::import_object($object, true), \midgard_connection::get_instance()->get_error_string());
        $refreshed = $this->make_object('midgard_topic', $object->guid);
        $this->assertNotEquals($object->id, $refreshed->id);
    }

    public function test_import_from_xml()
    {
        $prefix = dirname(__DIR__) . '/__files/replicator/';
        midgard_replicator::import_from_xml(file_get_contents($prefix . 'import_created_topic.xml'));
        $object = $this->make_object('midgard_topic', 'c1f17ea68e9911e4a07b8f9cdafb00b500b5');
        $this->assertSame('test', $object->extra);
    }

    public function test_import_from_xml_invalid_link()
    {
        $prefix = dirname(__DIR__) . '/__files/replicator/';
        $classname = connection::get_fqcn('midgard_topic');

        midgard_replicator::import_from_xml(file_get_contents($prefix . 'import_invalid_link.xml'));

        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('guid', '=', 'c1f17ea68e9911e4a07b8f9cdafb00b500b4');
        $results = $qb->execute();
        $this->assertCount(0, $results);

        midgard_replicator::import_from_xml(file_get_contents($prefix . 'import_invalid_link.xml'), true);

        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('guid', '=', 'c1f17ea68e9911e4a07b8f9cdafb00b500b4');
        $results = $qb->execute();
        $this->assertCount(1, $results);

        $object = $this->make_object('midgard_topic', 'c1f17ea68e9911e4a07b8f9cdafb00b500b4');
        $this->assertSame(0, $object->up);
    }

    public function test_import_from_xml_blob()
    {
        $prefix = dirname(__DIR__) . '/__files/replicator/';
        $classname = connection::get_fqcn('midgard_attachment');

        midgard_replicator::import_from_xml(file_get_contents($prefix . 'import_blob.xml'));

        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('guid', '=', '8708d5a091f011e49de5c7e771ceea9dea9d');
        $results = $qb->execute();
        $this->assertCount(0, $results);

        midgard_replicator::import_from_xml(file_get_contents($prefix . 'import_attachment_topic.xml'));
        midgard_replicator::import_from_xml(file_get_contents($prefix . 'import_attachment.xml'));
        midgard_replicator::import_from_xml(file_get_contents($prefix . 'import_blob.xml'));

        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('guid', '=', '8708d5a091f011e49de5c7e771ceea9dea9d');
        $results = $qb->execute();
        $this->assertCount(1, $results);

        $att = $this->make_object('midgard_attachment', '8708d5a091f011e49de5c7e771ceea9dea9d');
        $blob = new blob($att);
        $this->assertSame('test', $blob->read_content());
    }
}
