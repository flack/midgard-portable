<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_dbobject;

class midgard_query_builderTest extends testcase
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $classes = array(
            self::$em->getClassMetadata('midgard:midgard_topic'),
            self::$em->getClassMetadata('midgard:midgard_repligard'),
        );
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    /**
     * creates three topics for testing
     *
     * @param string $name_prefix
     * @return array
     */
    private function _create_topics($name_prefix)
    {
        $classname = self::$ns . '\\midgard_topic';
        $topics = array();

        $topics[0] = new $classname;
        $topics[0]->name = 'A_' . $name_prefix . 'testOne';
        $topics[0]->create();

        $topics[1] = new $classname;
        $topics[1]->name = 'B_' . $name_prefix . 'testTwo';
        $topics[1]->create();

        $topics[2] = new $classname;
        $topics[2]->name = 'C_' . $name_prefix . 'testThree';
        $topics[2]->create();

        return $topics;
    }

    public function test_execute()
    {
        $classname = self::$ns . '\\midgard_topic';
        $initial = $this->count_results($classname);

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        self::$em->clear();

        $qb = new \midgard_query_builder($classname);
        $results = $qb->execute();
        $this->assertInternalType('array', $results);
        $this->assertEquals($initial + 1, count($results));
        $this->assertInternalType('object', $results[0]);
        $this->assertEquals($classname, get_class($results[0]));
        $this->assertEquals($topic->metadata->created->format('Y-m-d H:i:s'), $results[0]->metadata->created->format('Y-m-d H:i:s'));
    }

    public function test_include_deleted()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic->delete();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('id', '=', $topic->id);
        $results = $qb->execute();
        $this->assertEquals(0, count($results));

        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('id', '=', $topic->id);
        $results = $qb->execute();
        $this->assertEquals(1, count($results));
    }

    public function test_add_order()
    {
        self::$em->clear();
        $classname = self::$ns . '\\midgard_topic';
        $this->purge_all($classname);
        $topics = $this->_create_topics(__FUNCTION__);

        // test order desc
        $qb = new \midgard_query_builder($classname);
        $qb->add_order('name', 'DESC');
        $results = $qb->execute();
        $first = array_shift($results);
        $this->assertEquals($topics[2]->name, $first->name);

        $qb = new \midgard_query_builder($classname);
        $qb->add_order('name', 'ASC');
        $results = $qb->execute();
        $first = array_shift($results);
        $this->assertEquals($topics[0]->name, $first->name);
    }

    public function test_add_constraint()
    {
        self::$em->clear();
        $classname = self::$ns . '\\midgard_topic';
        $this->purge_all($classname);

        $topic = new $classname;
        $topic->name = __FUNCTION__ . "testOne";
        $topic->extra = "special";
        $topic->create();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('guid', '=', $topic->guid);
        $results = $qb->execute();

        $this->assertEquals(1, count($results));
        $this->assertEquals($topic->id, $results[0]->id);

        // test metadata constraint
        $topic2 = new $classname;
        $topic2->name = __FUNCTION__ . "testTwo";
        $topic2->metadata_revision = 7;
        $topic2->extra = "";
        $topic2->create();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('metadata.revision', '=', 7);
        $results = $qb->execute();

        $this->assertEquals(1, count($results));
        $this->assertEquals($topic2->id, $results[0]->id);

        // test multiple constraints
        $topic3 = new $classname;
        $topic3->name = __FUNCTION__ . "testThee";
        $topic3->metadata_revision = 7;
        $topic3->extra = "special";
        $topic3->create();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('metadata.revision', '=', 7);
        $qb->add_constraint('extra', '=', 'special');
        $results = $qb->execute();

        $this->assertEquals(1, count($results));
        $this->assertEquals($topic3->id, $results[0]->id);
    }

    public function test_limit()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topics = $this->_create_topics(__FUNCTION__);

        $qb = new \midgard_query_builder($classname);
        $qb->set_limit(1);

        $results = $qb->execute();
        $this->assertEquals(1 , count($results));
    }

    public function test_offset()
    {
        self::$em->clear();
        $classname = self::$ns . '\\midgard_topic';
        $this->purge_all($classname);
        $topics = $this->_create_topics(__FUNCTION__);

        // get second entry
        $qb = new \midgard_query_builder($classname);
        $qb->add_order('name', 'ASC');
        $qb->set_offset(1);
        $qb->set_limit(1);
        $results = $qb->execute();
        $result = array_shift($results);
        $this->assertEquals($topics[1]->name, $result->name);

        // get third entry
        $qb = new \midgard_query_builder($classname);
        $qb->add_order('name', 'ASC');
        $qb->set_offset(2);
        $qb->set_limit(1);
        $results = $qb->execute();
        $result = array_shift($results);
        $this->assertEquals($topics[2]->name, $result->name);
    }

    public function test_grouping()
    {
        $classname = self::$ns . '\\midgard_topic';
        $extra = 'buildertest';
        $topic = new $classname;
        $topic->name = __FUNCTION__ . 'testOne';
        $topic->extra = $extra;
        $topic->create();

        $topic2 = new $classname;
        $topic2->name = __FUNCTION__ . 'testTwo';
        $topic2->extra = $extra;
        $topic2->create();

        $topic3 = new $classname;
        $topic3->name = __FUNCTION__ . 'testThree';
        $topic3->extra = $extra;
        $topic3->create();

        //should return no results
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('name', '=', $topic->name);
        $qb->add_constraint('name', '=', $topic2->name);

        $results = $qb->execute();
        $this->assertEquals(0 , count($results));

        //should return topic + topic2
        $qb = new \midgard_query_builder($classname);
        $qb->begin_group('OR');
            $qb->add_constraint('name', '=', $topic->name);
            $qb->add_constraint('name', '=', $topic2->name);
        $qb->end_group();

        $results = $qb->execute();
        $this->assertEquals(2 , count($results));

        //should only return topic
        $qb = new \midgard_query_builder($classname);
        $qb->begin_group('OR');
            $qb->add_constraint('name', '=', $topic->name);
            $qb->begin_group('AND');
                $qb->add_constraint('name', '=', $topic2->name);
                $qb->add_constraint('name', '=', $topic3->name);
            $qb->end_group();
        $qb->end_group();

        $results = $qb->execute();
        $this->assertEquals(1, count($results));

        //should only return topic2
        $qb = new \midgard_query_builder($classname);
        $qb->begin_group('OR');
            $qb->add_constraint('name', '=', $topic->name);
            $qb->add_constraint('name', '=', $topic2->name);
        $qb->end_group();
        $qb->begin_group('OR');
            $qb->add_constraint('name', '=', $topic2->name);
            $qb->add_constraint('name', '=', $topic3->name);
        $qb->end_group();

        $results = $qb->execute();

        $this->assertEquals(1, count($results));

        //should return topic + topic2
        $qb = new \midgard_query_builder($classname);
        $qb->begin_group('OR');
            $qb->add_constraint('name', '=' , $topic->name);
            $qb->begin_group('AND');
                $qb->add_constraint('name' , '=' , $topic2->name);
                $qb->add_constraint('extra' , '=', $extra);
            $qb->end_group();
        $qb->end_group();

        $results = $qb->execute();

        $this->assertEquals(2, count($results));

        //should return topic3+topic2
        $qb = new \midgard_query_builder($classname);
        $qb->begin_group('AND');
            $qb->add_constraint('extra', '=' , $extra);
            $qb->begin_group('OR');
                $qb->add_constraint('name' , '=' , $topic2->name);
                $qb->add_constraint('name' , '=' , $topic3->name);
            //$qb->end_group();
        //$qb->end_group();

        $results = $qb->execute();

        $this->assertEquals(2, count($results));
    }

    public function test_count()
    {
        self::$em->clear();
        $classname = self::$ns . '\\midgard_topic';
        $this->purge_all($classname);
        $topics = $this->_create_topics(__FUNCTION__);
        $orig_topic_count = count($topics);

        // count all results
        $qb = new \midgard_query_builder($classname);
        $qb_count = $qb->count();
        $this->assertEquals($orig_topic_count, $qb_count);

        // the qb should not be broken now.. try receiving the results that have been counted
        $results = $qb->execute();
        $this->assertEquals($orig_topic_count, count($results));

        // count with constraint
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint("guid", "=", $topics[0]->guid);
        $qb_count = $qb->count();
        $this->assertEquals(1, $qb_count);

        // test count with a soft-deleted topic
        $topic = array_shift($topics);
        $topic->delete();

        // count undeleted results
        self::$em->clear();
        $qb = new \midgard_query_builder($classname);
        $qb_count = $qb->count();

        $this->assertEquals($orig_topic_count-1, $qb_count);

        // count all results
        self::$em->clear();
        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb_count = $qb->count();

        $this->assertEquals($orig_topic_count, $qb_count);
    }
}