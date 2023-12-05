<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

class metadataTest extends testcase
{
    protected static $person;

    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();

        $classes = self::get_metadata([
            'midgard_topic',
            'midgard_article',
            'midgard_user',
            'midgard_person',
            'midgard_repligard',
        ]);
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
        self::$person = self::create_user();
    }

    public function test_alternate_fieldname()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();

        $this->assertFalse($topic->metadata->navnoentry);

        $topic->metadata->navnoentry = true;
        $this->assert_api('update', $topic);

        $topic = $this->make_object('midgard_topic', $topic->guid);
        $this->assertTrue($topic->metadata->navnoentry);
    }

    public function test_isset()
    {
        $topic = $this->make_object('midgard_topic');

        $this->assertTrue(isset($topic->metadata->published));
        $this->assertFalse(isset($topic->metadata->something));
    }

    public function test_create()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();

        $this->assertNotEquals('0000-01-01 00:00:00', $topic->metadata->created->format('Y-m-d H:i:s'));
        $this->assertEquals($topic->metadata->created->format('Y-m-d H:i:s'), $topic->metadata->revised->format('Y-m-d H:i:s'));
        $this->assertEquals(self::$person->guid, $topic->metadata->creator);
        $this->assertEquals($topic->metadata->creator, $topic->metadata->revisor);

        self::$em->clear();

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertEquals($topic->metadata->created->format('Y-m-d H:i:s'), $loaded->metadata->created->format('Y-m-d H:i:s'));
    }

    public function test_update()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();

        $person = self::create_user();

        $topic->name = __FUNCTION__ . '2'; // <== TODO: Without changing anything, doctrine won't update. Problem?
        $created = (int) $topic->metadata->created->format('U');
        while ($created == time()) {
            usleep(100);
        }
        $topic->update();

        $this->assertGreaterThan($created, (int) $topic->metadata->revised->format('U'));
        $this->assertEquals(1, $topic->metadata->revision);

        self::$em->clear();

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertEquals($topic->metadata->revision, $loaded->metadata->revision);
        $this->assertEquals($person->guid, $loaded->metadata->revisor);
        $this->assertEquals(309, $topic->metadata->size);
    }

    public function test_delete()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $stat = $topic->create();
        $this->assertTrue($stat, \midgard_connection::get_instance()->get_error_string());
        $stat = $topic->delete();
        $this->assertTrue($stat, \midgard_connection::get_instance()->get_error_string());

        $this->assertNotEquals('0000-01-01 00:00:00', $topic->metadata->revised->format('Y-m-d H:i:s'));
        $this->assertEquals(1, $topic->metadata->revision, 'Unexpected revision number');
        $this->assertTrue($topic->metadata->deleted);
        $this->assertEquals(306, $topic->metadata->size);
    }
}
