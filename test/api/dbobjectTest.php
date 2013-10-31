<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\storage\connection;

class dbobjectTest extends testcase
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = array(
            $factory->getMetadataFor('midgard:midgard_language'),
            $factory->getMetadataFor('midgard:midgard_topic'),
            $factory->getMetadataFor('midgard:midgard_repligard'),
        );
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_get_empty_link_property()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $this->assertEquals(0, $topic->up);
    }

    public function test_set()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;

        $topic->title = null;
        $topic->score = null;
        $topic->metadata_published = null;
        $this->assertSame('', $topic->title);
        $this->assertSame(0, $topic->score);
        $this->assertInstanceOf('midgard_datetime', $topic->metadata_published);
        $this->assertEquals('0001-01-01 00:00:00', $topic->metadata_published->format('Y-m-d H:i:s'));
    }

    public function test_get()
    {
        $classname = self::$ns . '\\midgard_topic';
        $parent = new $classname;
        $parent->name = __FUNCTION__;
        $parent->create();

        $topic = new $classname;
        $topic->up = $parent->id;
        $topic->create();
        self::$em->clear();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('id', '=', $topic->id);
        $results = $qb->execute();

        $this->assertSame($topic->score, $results[0]->score);
        $this->assertSame($topic->up, $results[0]->up);
    }

    public function test_isset()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;

        $this->assertTrue(isset($topic->title));
        $this->assertFalse(isset($topic->something));
    }
}