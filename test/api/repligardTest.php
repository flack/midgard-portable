<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\storage\subscriber;

class midgard_repligardTest extends testcase
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

    public function test_class()
    {
        $classname = self::$ns . '\\midgard_repligard';
        $object = new $classname;
        $this->assertInstanceOf('\\midgard\\portable\\api\\dbobject', $object);
        $this->assertNotInstanceOf('\\midgard\\portable\\api\\object', $object);
        $this->assertNotInstanceOf('\\midgard\\portable\\storage\\interfaces\\metadata', $object);
    }

    public function test_create()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        self::$em->clear();
        $repligard_entry = self::$em->getRepository('midgard:midgard_repligard')->findOneBy(array('guid' => $topic->guid));
        $this->assertInstanceOf(self::$ns . '\\midgard_repligard', $repligard_entry);
        $this->assertFalse(property_exists($repligard_entry, 'metadata'));
        $this->assertEquals(subscriber::ACTION_CREATE, $repligard_entry->object_action);
        $this->assertEquals('midgard_topic', $repligard_entry->typename);
    }

    public function test_update()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->create();
        self::$em->clear();

        $topic->name = __FUNCTION__;
        $topic->update();
        self::$em->clear();

        $repligard_entry = self::$em->getRepository('midgard:midgard_repligard')->findOneBy(array('guid' => $topic->guid));
        $this->assertInstanceOf(self::$ns . '\\midgard_repligard', $repligard_entry);
        $this->assertFalse(property_exists($repligard_entry, 'metadata'));
        $this->assertEquals(subscriber::ACTION_UPDATE, $repligard_entry->object_action);
        $this->assertEquals('midgard_topic', $repligard_entry->typename);
    }

    public function test_delete()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->create();
        self::$em->clear();

        $topic->delete();
        self::$em->clear();

        $repligard_entry = self::$em->getRepository('midgard:midgard_repligard')->findOneBy(array('guid' => $topic->guid));
        $this->assertInstanceOf(self::$ns . '\\midgard_repligard', $repligard_entry);
        $this->assertFalse(property_exists($repligard_entry, 'metadata'));
        $this->assertEquals(subscriber::ACTION_DELETE, $repligard_entry->object_action);
        $this->assertEquals('midgard_topic', $repligard_entry->typename);
    }

    public function test_purge()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->create();
        self::$em->clear();

        $topic->purge();
        self::$em->clear();

        $repligard_entry = self::$em->getRepository('midgard:midgard_repligard')->findOneBy(array('guid' => $topic->guid));
        $this->assertInstanceOf(self::$ns . '\\midgard_repligard', $repligard_entry);
        $this->assertFalse(property_exists($repligard_entry, 'metadata'));
        $this->assertEquals(subscriber::ACTION_PURGE, $repligard_entry->object_action);
        $this->assertEquals('midgard_topic', $repligard_entry->typename);
    }
}
