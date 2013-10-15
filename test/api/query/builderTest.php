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

    public function test_add_constraint()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('guid', '=', $topic->guid);
        $results = $qb->execute();

        $this->assertEquals(1, count($results));
        $this->assertEquals($topic->id, $results[0]->id);
    }
    
    public function test_limit()
    {
        $classname = self::$ns . '\\midgard_topic';
        
        $topic = new $classname;
        $topic->name = __FUNCTION__ . 'testOne';
        $topic->create();
        
        $topic2 = new $classname;
        $topic2->name = __FUNCTION__ . 'testTwo';
        $topic2->create();
        
        $topic3 = new $classname;
        $topic3->name = __FUNCTION__ . 'testThree';
        $topic3->create();
        
        $qb = new \midgard_query_builder($classname);
        $qb->set_limit(1);
        
        $results = $qb->execute();
        
        $this->assertEquals(1 , count($results));
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
}