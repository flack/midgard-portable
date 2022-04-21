<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use Doctrine\ORM\UnitOfWork;
use midgard\portable\storage\connection;

class dbobjectTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();
        $classes = self::get_metadata([
            'midgard_language',
            'midgard_topic',
            'midgard_repligard',
        ]);
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_get_empty_link_property()
    {
        $topic = $this->make_object('midgard_topic');
        $this->assertEquals(0, $topic->up);
    }

    public function test_set()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->title = null;
        $topic->code = null;
        $topic->score = null;
        $topic->up = 0;
        $topic->styleInherit = null;
        $topic->metadata_published = null;
        $topic->floatField = null;

        $this->assertSame('', $topic->title);
        $this->assertSame('', $topic->code);
        $this->assertSame(0, $topic->score);
        $this->assertSame(0, $topic->lang);
        $this->assertSame(0, $topic->up);
        $this->assertSame(0.0, $topic->floatField);
        $this->assertSame(false, $topic->styleInherit);
        $this->assertInstanceOf('midgard_datetime', $topic->metadata_published);
        $this->assertEquals('0001-01-01 00:00:00', $topic->metadata_published->format('Y-m-d H:i:s'));

        $topic->up = 9999999;
        $this->assertSame(9999999, $topic->up);
        $topic->floatField = 2;
        $this->assertSame(2.0, $topic->floatField);
        $topic->metadata_published = '2012-10-11 01:11:22';
        $this->assertEquals('2012-10-11T01:11:22+00:00', (string) $topic->metadata_published);
        $topic->metadata_published = '0000-00-00 00:00:00';
        $this->assertEquals('0001-01-01T00:00:00+00:00', (string) $topic->metadata_published);
    }

    public function test_set_nonexistent()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->nonexistent_property = 'xxx';
        $this->assertTrue(property_exists($topic, 'nonexistent_property'));
        $this->assertSame('xxx', $topic->nonexistent_property);
    }

    public function test_get_id()
    {
        $topic = $this->make_object('midgard_topic');
        //This checks the value with reflection internally and expects null
        $this->assertSame(UnitOfWork::STATE_NEW, self::$em->getUnitOfWork()->getEntityState($topic));
        $this->assertSame(0, $topic->id);
    }

    public function test_get()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $parent = $this->make_object('midgard_topic');
        $parent->name = __FUNCTION__;
        $parent->create();

        $topic = $this->make_object('midgard_topic');
        $topic->up = $parent->id;
        $topic->create();
        self::$em->clear();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('id', '=', $topic->id);
        $results = $qb->execute();

        $this->assertSame($topic->score, $results[0]->score);
        //This confuses reference proxies. Do we need to support this case?
        //$parent->purge();
        $this->assertSame($topic->up, $results[0]->up);
    }

    public function test_get_default_date()
    {
        $topic = $this->make_object('midgard_topic');

        // This simulates data loaded from old Midgard 1 databases
        $ref = new \ReflectionClass($topic);
        $published = $ref->getProperty('birthdate');
        $published->setAccessible(true);
        $published->setValue($topic, new \midgard_datetime('0000-00-00 00:00:00'));

        $this->assertSame('0001-01-01 00:00:00', $topic->birthdate->format('Y-m-d H:i:s'));
    }

    public function test_isset()
    {
        $topic = $this->make_object('midgard_topic');

        $this->assertTrue(isset($topic->title));
        $this->assertFalse(isset($topic->something));
    }
}
