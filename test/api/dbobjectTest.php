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
        $topic->up = 0;
        $topic->styleInherit = null;
        $topic->metadata_published = null;
        $topic->float = null;

        $this->assertSame('', $topic->title);
        $this->assertSame(0, $topic->score);
        $this->assertSame(0, $topic->lang);
        $this->assertSame(0, $topic->up);
        $this->assertSame(0.0, $topic->float);
        $this->assertSame(false, $topic->styleInherit);
        $this->assertInstanceOf('midgard_datetime', $topic->metadata_published);
        $this->assertEquals('0001-01-01 00:00:00', $topic->metadata_published->format('Y-m-d H:i:s'));

        $topic->up = 9999999;
        $this->assertSame(9999999, $topic->up);
        $topic->float = 2;
        $this->assertSame(2.0, $topic->float);
        $topic->metadata_published = '2012-10-11 01:11:22';
        $this->assertEquals('2012-10-11T01:11:22+00:00', (string) $topic->metadata_published);
        $topic->metadata_published = '0000-00-00 00:00:00';
        $this->assertEquals('0001-01-01T00:00:00+00:00', (string) $topic->metadata_published);
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