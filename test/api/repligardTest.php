<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\storage\subscriber;
use midgard\portable\api\dbobject;
use midgard\portable\api\mgdobject;
use midgard\portable\storage\interfaces\metadata;
use midgard\portable\storage\connection;

class midgard_repligardTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();

        $classes = self::get_metadata([
            'midgard_topic',
            'midgard_repligard',
            'midgard_article',
        ]);
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_class()
    {
        $object = $this->make_object('midgard_repligard');
        $this->assertInstanceOf(dbobject::class, $object);
        $this->assertNotInstanceOf(mgdobject::class, $object);
        $this->assertNotInstanceOf(metadata::class, $object);
    }

    public function test_create()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        self::$em->clear();
        $repligard_entry = self::$em->getRepository(connection::get_fqcn('midgard_repligard'))->findOneBy(['guid' => $topic->guid]);
        $this->assertInstanceOf(connection::get_fqcn('midgard_repligard'), $repligard_entry);
        $this->assertFalse(property_exists($repligard_entry, 'metadata'));
        $this->assertEquals(subscriber::ACTION_CREATE, $repligard_entry->object_action);
        $this->assertEquals('midgard_topic', $repligard_entry->typename);
    }

    public function test_update()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        self::$em->clear();

        $topic->name = __FUNCTION__;
        $topic->update();
        self::$em->clear();

        $repligard_entry = self::$em->getRepository(connection::get_fqcn('midgard_repligard'))->findOneBy(['guid' => $topic->guid]);
        $this->assertInstanceOf(connection::get_fqcn('midgard_repligard'), $repligard_entry);
        $this->assertFalse(property_exists($repligard_entry, 'metadata'));
        $this->assertEquals(subscriber::ACTION_UPDATE, $repligard_entry->object_action);
        $this->assertEquals('midgard_topic', $repligard_entry->typename);
    }

    public function test_delete()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        self::$em->clear();

        $topic->delete();
        self::$em->clear();

        $repligard_entry = self::$em->getRepository(connection::get_fqcn('midgard_repligard'))->findOneBy(['guid' => $topic->guid]);
        $this->assertInstanceOf(connection::get_fqcn('midgard_repligard'), $repligard_entry);
        $this->assertFalse(property_exists($repligard_entry, 'metadata'));
        $this->assertEquals(subscriber::ACTION_DELETE, $repligard_entry->object_action);
        $this->assertEquals('midgard_topic', $repligard_entry->typename);
    }

    public function test_purge()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        self::$em->clear();

        $topic->purge();
        self::$em->clear();

        $repligard_entry = self::$em->getRepository(connection::get_fqcn('midgard_repligard'))->findOneBy(['guid' => $topic->guid]);
        $this->assertInstanceOf(connection::get_fqcn('midgard_repligard'), $repligard_entry);
        $this->assertFalse(property_exists($repligard_entry, 'metadata'));
        $this->assertEquals(subscriber::ACTION_PURGE, $repligard_entry->object_action);
        $this->assertEquals('midgard_topic', $repligard_entry->typename);
    }
}
