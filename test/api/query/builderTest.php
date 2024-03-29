<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;


use midgard\portable\storage\connection;

class midgard_query_builderTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();
        $classes = self::get_metadata([
            'midgard_topic',
            'midgard_parameter',
            'midgard_article',
            'midgard_language',
            'midgard_repligard',
            'midgard_person',
            'midgard_user'
        ]);
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    /**
     * creates three topics for testing
     */
    private function _create_topics(string $name_prefix) : array
    {
        $topics = [];

        $topics[0] = $this->make_object('midgard_topic');
        $topics[0]->name = 'A_' . $name_prefix . 'testOne';
        $topics[0]->create();

        $topics[1] = $this->make_object('midgard_topic');
        $topics[1]->up = $topics[0]->id;
        $topics[1]->name = 'B_' . $name_prefix . 'testTwo';
        $topics[1]->create();

        $topics[2] = $this->make_object('midgard_topic');
        $topics[2]->up = $topics[1]->id;
        $topics[2]->name = 'C_' . $name_prefix . 'testThree';
        $topics[2]->create();

        return $topics;
    }

    public function test_iterate()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $topics = $this->_create_topics(__FUNCTION__);
        $this->assert_api('delete', $topics[2]);
        $initial = $this->count_results('midgard_topic');
        $found = 0;

        $qb = new \midgard_query_builder($classname);
        foreach ($qb->iterate() as $result) {
            $found++;
        }

        $this->assertEquals($found, $initial);
    }

    public function test_execute()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $initial = $this->count_results('midgard_topic');

        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        self::$em->clear();

        $qb = new \midgard_query_builder($classname);
        $qb->add_order('id', 'DESC'); // out new object is the last one, i.e. the one with the highest ID
        $results = $qb->execute();
        $this->assertIsArray($results);
        $this->assertCount($initial + 1, $results);
        $this->assertIsObject($results[0]);
        $this->assertEquals($classname, get_class($results[0]));
        $this->assertEquals($topic->metadata->created->format('Y-m-d H:i:s'), $results[0]->metadata->created->format('Y-m-d H:i:s'));
    }

    public function test_include_deleted()
    {
        $classname = connection::get_fqcn('midgard_topic');

        $topic = $this->make_object('midgard_topic');;
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic->delete();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('id', '=', $topic->id);
        $results = $qb->execute();
        $this->assertCount(0, $results);

        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb->add_constraint('id', '=', $topic->id);
        $results = $qb->execute();
        $this->assertCount(1, $results);
    }

    public function test_add_order()
    {
        self::$em->clear();
        $classname = connection::get_fqcn('midgard_topic');
        $this->purge_all('midgard_topic');
        $topics = $this->_create_topics(__FUNCTION__);

        // test order desc
        $qb = new \midgard_query_builder($classname);
        $stat = $qb->add_order('name', 'DESC');
        $this->assertTrue($stat);
        $results = $qb->execute();
        $first = array_shift($results);
        $this->assertEquals($topics[2]->name, $first->name);

        // test order ASC
        $qb = new \midgard_query_builder($classname);
        $qb->add_order('name', 'ASC');
        $results = $qb->execute();
        $first = array_shift($results);
        $this->assertEquals($topics[0]->name, $first->name);

        // test two orders
        $qb = new \midgard_query_builder($classname);
        $qb->add_order('name', 'DESC');
        $qb->add_order('id', 'ASC');
        $results = $qb->execute();
        $first = array_shift($results);
        $this->assertEquals($topics[2]->name, $first->name);

        // test invalid orders
        $this->assertFalse($qb->add_order('name', 'xxx'));
        $this->assertFalse($qb->add_order('xxx', 'ASC'));

        // test order with guid link field
        self::$em->clear();

        $this->purge_all('midgard_person');

        $person = $this->make_object('midgard_person');
        $person->firstname = "John";
        $person->create();

        $person2 = $this->make_object('midgard_person');
        $person2->firstname = "Bob";
        $person2->create();

        $this->purge_all('midgard_user');

        $user = $this->make_object('midgard_user');
        $user->authtype = 'Legacy';
        $user->person = $person->guid;
        $user->create();

        $user2 = $this->make_object('midgard_user');
        $user2->authtype = 'Legacy';
        $user2->person = $person2->guid;
        $user2->create();

        $qb = new \midgard_query_builder(connection::get_fqcn('midgard_user'));
        $stat = $qb->add_order('person.firstname', 'ASC');
        $this->assertTrue($stat);

        $results = $qb->execute();
        $this->assertCount(2, $results);
        $first = array_shift($results);

        // first result should be user2 (Bob)
        $this->assertEquals($user2->id, $first->id);
    }

    public function test_add_constraint_with_property()
    {
        self::$em->clear();
        $this->purge_all('midgard_topic');

        $topic = $this->make_object('midgard_topic');
        $topic->name = "A_" . __FUNCTION__ . "testOne";
        $topic->extra = $topic->name;
        $topic->create();

        $qb = new \midgard_query_builder(connection::get_fqcn('midgard_topic'));
        $this->assertTrue($qb->add_constraint_with_property('name', '=', 'extra'));
        $results = $qb->execute();

        $this->assertCount(1, $results);
        $this->assertEquals($topic->id, $results[0]->id);
    }

    public function test_add_constraint()
    {
        self::$em->clear();
        $classname = connection::get_fqcn('midgard_topic');
        $this->purge_all('midgard_topic');

        // test single constraint
        $topic = $this->make_object('midgard_topic');
        $topic->name = "A_" . __FUNCTION__ . "testOne";
        $topic->extra = "special";
        $topic->create();

        $qb = new \midgard_query_builder($classname);
        $stat = $qb->add_constraint('guid', '=', $topic->guid);
        $this->assertTrue($stat);
        $results = $qb->execute();

        $this->assertCount(1, $results);
        $this->assertEquals($topic->id, $results[0]->id);

        // test metadata constraint
        $topic2 = $this->make_object('midgard_topic');
        $topic2->name = "B_" . __FUNCTION__ . "testTwo";
        $topic2->metadata_revision = 7;
        $topic2->extra = "";
        $topic2->create();
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('metadata.revision', '=', 7);
        $results = $qb->execute();

        $this->assertCount(1, $results);
        $this->assertEquals($topic2->id, $results[0]->id);

        // test constraint on join field
        $topic3 = $this->make_object('midgard_topic');
        $topic3->name = "C_" . __FUNCTION__ . "testThee";
        $topic3->metadata_revision = 7;
        $topic3->extra = "special";
        $topic3->up = $topic2->id;
        $topic3->create();

        // this should find topic3
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('up', '=', $topic2->id);
        $results = $qb->execute();

        $this->assertCount(1, $results);
        $this->assertEquals($topic3->id, $results[0]->id);

        // test multiple constraints
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('metadata.revision', '=', 7);
        $qb->add_constraint('extra', '=', 'special');
        $results = $qb->execute();

        $this->assertCount(1, $results);
        $this->assertEquals($topic3->id, $results[0]->id);

        // test join
        // create two languages and link them to the topics
        // then we query for topics with a certain language id
        $lang = $this->make_object('midgard_language');
        $lang->name = "german";
        $lang->code = "de";
        $lang->metadata_revision = 7;
        $lang->create();

        $lang2 = $this->make_object('midgard_language');
        $lang2->name = "english";
        $lang2->code = "en";
        $lang2->create();

        $topic->lang = $lang->id;
        $topic->update();

        $topic2->lang = $lang->id;
        $topic2->update();

        $topic3->lang = $lang2->id;
        $topic3->update();

        // we should find just one topic (topic3) for lang2
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('lang.id', '=', $lang2->id);
        $results = $qb->execute();

        $this->assertCount(1, $results);
        $this->assertEquals($topic3->id, $results[0]->id);

        // test with join (with metadata)
        $qb = new \midgard_query_builder($classname);
        // this should find the german topics (topic1+topic2)
        $qb->add_constraint("lang.metadata.revision", "=", 7);
        $qb->add_order("name");
        $results = $qb->execute();

        $this->assertCount(2, $results);
        $this->assertEquals($topic->id, $results[0]->id);
        $this->assertEquals($topic2->id, $results[1]->id);
    }

    public function test_add_constraint_nonexistant()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $qb = new \midgard_query_builder($classname);
        $this->assertFalse($qb->add_constraint('xxx', '=', 0));
        $this->assertFalse($qb->add_constraint('metadata.xxx', '=', 0));
        $this->assertFalse($qb->add_constraint('up.xxx', '=', 0));
    }

    public function test_add_constraint_invalid_operator()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $qb = new \midgard_query_builder($classname);
        $this->assertFalse($qb->add_constraint('id', '', 0));
        $this->assertFalse($qb->add_constraint('id', '!=', 0));
        $this->assertFalse($qb->add_constraint('id', 'xxx', 0));
    }

    public function test_add_constraint_parameter()
    {
        $classname_t = connection::get_fqcn('midgard_topic');
        $topics = $this->_create_topics(__FUNCTION__);
        $topics[0]->set_parameter('test_domain', 'test', 'test_value');

        $qb = new \midgard_query_builder($classname_t);
        $this->assertTrue($qb->add_constraint('parameter.domain', '=', 'test_domain'));
        $this->assertTrue($qb->add_constraint('parameter.name', '=', 'test'));
        $this->assertTrue($qb->add_constraint('parameter.value', '=', 'test_value'));

        $results = $qb->execute();
        $this->assertCount(1, $results);
    }

    public function test_limit()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $this->_create_topics(__FUNCTION__);

        $qb = new \midgard_query_builder($classname);
        $qb->set_limit(1);

        $results = $qb->execute();
        $this->assertCount(1, $results);
    }

    public function test_offset()
    {
        self::$em->clear();
        $classname = connection::get_fqcn('midgard_topic');
        $this->purge_all('midgard_topic');
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
        $classname = connection::get_fqcn('midgard_topic');
        $extra = 'buildertest';
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__ . 'testOne';
        $topic->extra = $extra;
        $topic->create();

        $topic2 = $this->make_object('midgard_topic');
        $topic2->name = __FUNCTION__ . 'testTwo';
        $topic2->extra = $extra;
        $topic2->create();

        $topic3 = $this->make_object('midgard_topic');
        $topic3->name = __FUNCTION__ . 'testThree';
        $topic3->extra = $extra;
        $topic3->create();

        //should return no results
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('name', '=', $topic->name);
        $qb->add_constraint('name', '=', $topic2->name);

        $results = $qb->execute();
        $this->assertCount(0, $results);

        //should return topic + topic2
        $qb = new \midgard_query_builder($classname);
        $qb->begin_group('OR');
        $qb->add_constraint('name', '=', $topic->name);
        $qb->add_constraint('name', '=', $topic2->name);
        $qb->end_group();

        $results = $qb->execute();
        $this->assertCount(2, $results);

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
        $this->assertCount(1, $results);

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

        $this->assertCount(1, $results);

        //should return topic + topic2
        $qb = new \midgard_query_builder($classname);
        $qb->begin_group('OR');
        $qb->add_constraint('name', '=', $topic->name);
        $qb->begin_group('AND');
        $qb->add_constraint('name', '=', $topic2->name);
        $qb->add_constraint('extra', '=', $extra);
        $qb->end_group();
        $qb->end_group();

        $results = $qb->execute();

        $this->assertCount(2, $results);

        //should return topic3+topic2
        $qb = new \midgard_query_builder($classname);
        $qb->begin_group('AND');
        $qb->add_constraint('extra', '=', $extra);
        $qb->begin_group('OR');
        $qb->add_constraint('name', '=', $topic2->name);
        $qb->add_constraint('name', '=', $topic3->name);
            //$qb->end_group();
        //$qb->end_group();

        $results = $qb->execute();

        $this->assertCount(2, $results);
    }

    public function test_count()
    {
        self::$em->clear();
        $classname = connection::get_fqcn('midgard_topic');
        $this->purge_all('midgard_topic');
        $topics = $this->_create_topics(__FUNCTION__);
        $orig_topic_count = count($topics);

        // count all results
        $qb = new \midgard_query_builder($classname);
        $qb_count = $qb->count();
        $this->assertEquals($orig_topic_count, $qb_count);

        // the qb should not be broken now.. try receiving the results that have been counted
        $results = $qb->execute();
        $this->assertCount($orig_topic_count, $results);

        // count with constraint
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint("guid", "=", $topics[0]->guid);
        $qb_count = $qb->count();
        $this->assertEquals(1, $qb_count);

        // test count with a soft-deleted topic
        $topic = array_pop($topics);
        $topic->delete();

        // count undeleted results
        self::$em->clear();
        $qb = new \midgard_query_builder($classname);
        $qb_count = $qb->count();

        $this->assertEquals($orig_topic_count - 1, $qb_count);

        // count all results
        self::$em->clear();
        $qb = new \midgard_query_builder($classname);
        $qb->include_deleted();
        $qb_count = $qb->count();

        $this->assertEquals($orig_topic_count, $qb_count);
    }

    public function test_empty_group()
    {
        $this->purge_all('midgard_topic');
        self::$em->clear();

        $qb = new \midgard_query_builder(connection::get_fqcn('midgard_topic'));
        $qb->begin_group('OR');
        $qb->end_group();
        $this->assertEquals(0, $qb->count());
    }

    public function test_useless_end_group()
    {
        $qb = new \midgard_query_builder('midgard_topic');
        $this->assertFalse($qb->end_group());
    }

    public function test_invalid_begin_group()
    {
        $qb = new \midgard_query_builder('midgard_topic');
        $this->assertFalse($qb->begin_group('XX'));
    }

    public function test_null_constraints()
    {
        self::$em->clear();
        $classname = connection::get_fqcn('midgard_topic');
        $this->purge_all('midgard_topic');
        $topic = $this->make_object('midgard_topic');
        $topic->name = uniqid(__FUNCTION__);
        $stat = $topic->create();
        $this->assertTrue($stat);
        self::$em->clear();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('up', '<>', 0);
        $this->assertEquals(0, $qb->count(), "We should not find any topics");

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('up', '>', 0);
        $this->assertEquals(0, $qb->count(), "We should not find any topics");
    }

    public function test_in_constraint()
    {
        self::$em->clear();
        $classname = connection::get_fqcn('midgard_topic');
        $this->purge_all('midgard_topic');
        $topics = $this->_create_topics(__FUNCTION__);
        self::$em->clear();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('id', 'IN', [$topics[0]->id, $topics[1]->id]);
        $this->assertEquals(2, $qb->count());

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('id', 'NOT IN', [$topics[0]->id, $topics[1]->id]);
        $results = $qb->execute();
        $this->assertCount(1, $results);
        $this->assertEquals($topics[2]->id, $results[0]->id);

        $qb = new \midgard_query_builder($classname);
        // Array with string key is what collector normally returns
        $qb->add_constraint('id', 'IN', [$topics[0]->guid => $topics[0]->id]);
        $this->assertEquals(1, $qb->count());

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('up', 'NOT IN', [$topics[0]->id, $topics[1]->id]);
        $results = $qb->execute();
        $this->assertCount(1, $results);
        $this->assertEquals($topics[0]->id, $results[0]->id);

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('up', 'IN', [$topics[0]->up]);
        $results = $qb->execute();
        $this->assertCount(1, $results);
        $this->assertEquals($topics[0]->id, $results[0]->id);
    }

    public function test_intree_constraint()
    {
        self::$em->clear();
        $classname = connection::get_fqcn('midgard_topic');
        $this->purge_all('midgard_topic');
        $topics = $this->_create_topics(__FUNCTION__);

        $article_class = connection::get_fqcn('midgard_article');

        $article = $this->make_object('midgard_article');
        $article->topic = $topics[2]->id;
        $article->create();
        self::$em->clear();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('up', 'INTREE', $topics[0]->id);
        $this->assertEquals(2, $qb->count());

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('up', 'INTREE', $topics[1]->id);
        $results = $qb->execute();
        $this->assertCount(1, $results);
        $this->assertEquals($topics[2]->id, $results[0]->id);

        $qb = new \midgard_query_builder($article_class);
        $qb->add_constraint('topic', 'INTREE', $topics[0]->id);
        $results = $qb->execute();
        $this->assertCount(1, $results);
        $this->assertEquals($article->id, $results[0]->id);

        $qb = new \midgard_query_builder($article_class);
        $qb->add_constraint('topic.up', 'INTREE', $topics[0]->id);
        $results = $qb->execute();
        $this->assertCount(1, $results);
        $this->assertEquals($article->id, $results[0]->id);
    }

    public function test_empty_link_constraint()
    {
        self::$em->clear();
        $classname = connection::get_fqcn('midgard_topic');
        $this->purge_all('midgard_topic');

        $topic = $this->make_object('midgard_topic');
        $topic->name = "A_" . __FUNCTION__ . "testOne";
        $topic->extra = "special";
        $topic->create();

        $qb = new \midgard_query_builder($classname);
        $stat = $qb->add_constraint('up', '=', 0);
        $this->assertTrue($stat);
        $this->assertEquals(1, $qb->count());

        $qb = new \midgard_query_builder($classname);
        $stat = $qb->add_constraint('up', '=', $topic->up);
        $this->assertTrue($stat);
        $this->assertEquals(1, $qb->count());

        $qb = new \midgard_query_builder($classname);
        $stat = $qb->add_constraint('up', '<>', 0);
        $this->assertTrue($stat);
        $this->assertEquals(0, $qb->count());

        $topic2 = $this->make_object('midgard_topic');
        $topic2->name = "A_" . __FUNCTION__ . "testTwo";
        $topic2->up = $topic->id;
        $topic2->create();

        $qb = new \midgard_query_builder($classname);
        $stat = $qb->add_constraint('up', '<>', 0);
        $this->assertTrue($stat);
        $this->assertEquals(1, $qb->count());
    }

    public function test_aliased_fieldname()
    {
        self::$em->clear();
        $classname = connection::get_fqcn('midgard_topic');
        $this->purge_all('midgard_topic');
        $topics = $this->_create_topics(__FUNCTION__);

        $cm = self::$em->getClassMetadata($classname);
        $cm->midgard['field_aliases'] = ['name_alias' => 'name'];

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('name_alias', '=', $topics[0]->name);
        $this->assertEquals(1, $qb->count());
    }
}
