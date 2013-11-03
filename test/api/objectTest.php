<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

class objectTest extends testcase
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();

        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = $factory->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_construct()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
    }

    public function test_load()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->name = __FUNCTION__;

        $topic->create();
        self::$em->clear();

        $loaded = new $classname($topic->id);
        $this->assertEquals($topic->id, $loaded->id);
        $this->assertNotEquals('', $loaded->guid);
        $this->assertEquals($topic->name, $loaded->name);

        $loaded2 = new $classname($topic->guid);
        $this->assertEquals($topic->id, $loaded2->id);
        $this->assertEquals($topic->name, $loaded2->name);
    }

    public function test_load_deleted()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic->delete();
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());

        // try from identity map
        $e = null;
        try
        {
            $loaded = new $classname($topic->id);
        }
        catch ( \midgard_error_exception $e){}

        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_OBJECT_DELETED, \midgard_connection::get_instance()->get_error());

        // try from db
        self::$em->clear();
        $e = null;
        try
        {
            $loaded = new $classname($topic->id);
        }
        catch ( \midgard_error_exception $e){}

        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, \midgard_connection::get_instance()->get_error());
    }

    public function test_load_purged()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        $id = $topic->id;
        $topic->delete();
        $topic->purge();
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());

        $e = null;
        try
        {
            $loaded = new $classname($id);
        }
        catch ( \midgard_error_exception $e){}

        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, \midgard_connection::get_instance()->get_error());

        $e = null;
        try
        {
            $proxy = self::$em->getReference($classname, $id);
            $loaded = new $classname($id);
        }
        catch ( \midgard_error_exception $e){}

        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_OBJECT_PURGED, \midgard_connection::get_instance()->get_error());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_load_invalid_guid()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname('xxx');
    }

    public function test_get_by_id()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        self::$em->clear();

        $loaded = new $classname;
        $stat = $loaded->get_by_id($topic->id);
        $this->assertTrue($stat);
        $this->assertEquals($topic->id, $loaded->id);
        $this->assertEquals($topic->name, $loaded->name);
    }

    /**
     * @expectedException midgard_error_exception
     */
    public function test_load_unknown_id()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname(999999999);
    }

    public function test_get_by_guid()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        self::$em->clear();

        $loaded = new $classname;
        $stat = $loaded->get_by_guid($topic->guid);
        $this->assertTrue($stat);
        $this->assertEquals($topic->id, $loaded->id);
        $this->assertEquals($topic->name, $loaded->name);
    }

    public function test_create()
    {
        $classname = self::$ns . '\\midgard_topic';
        $initial = $this->count_results($classname);

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $stat = $topic->create();
        $this->assertTrue($stat);
        $this->assertFalse(empty($topic->guid), 'GUID empty');
        $this->assertEquals($initial + 1, $this->count_results($classname));
        $this->assertGreaterThan($initial, $topic->id);

        $topic2 = new $classname;
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__ . '-2';
        $stat = $topic2->create();
        $this->assertTrue($stat);
        $stat = $topic2->create();
        $this->assertFalse($stat);
        $this->assertEquals($initial + 2, $this->count_results($classname));
        $this->assertEquals($topic->id + 1, $topic2->id);

        $topic3 = new $classname;
        $topic3->up = $topic->id;
        $topic3->name = __FUNCTION__ . '-3';
        $stat = $topic3->create();
        $this->assertTrue($stat);
    }

    public function test_update()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->create();

        $topic->name = __FUNCTION__ . 'xxx';
        $stat = $topic->update();
        $this->assertTrue($stat);
        self::$em->clear();

        $loaded = new $classname($topic->id);
        $this->assertEquals($topic->name, $loaded->name);
        $this->assertEquals('', $loaded->title);
    }

    public function test_delete()
    {
        $classname = self::$ns . '\\midgard_topic';

        $initial = $this->count_results($classname);
        $initial_all = $this->count_results($classname, true);

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        $stat = $topic->delete();
        $this->assertTrue($stat);
        $this->assertEquals($initial, $this->count_results($classname));

        $all = $this->count_results($classname, true);
        $this->assertEquals($initial_all + 1, $all);
    }

    public function test_has_dependents()
    {
        $classname = self::$ns . '\\midgard_topic';

        $initial = $this->count_results($classname);
        $initial_all = $this->count_results($classname, true);

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic2 = new $classname;
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__;
        $topic2->create();

        $this->assertTrue($topic->has_dependents());
    }

    public function test_delete_with_dependents()
    {
        $classname = self::$ns . '\\midgard_topic';

        $initial = $this->count_results($classname);
        $initial_all = $this->count_results($classname, true);

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic2 = new $classname;
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__;
        $topic2->create();

        $stat = $topic->delete();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_HAS_DEPENDANTS, \midgard_connection::get_instance()->get_error());

        $stat = $topic2->delete();
        $this->assertTrue($stat);
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());
    }

    public function test_purge()
    {
        $classname = self::$ns . '\\midgard_topic';
        $initial = $this->count_results($classname, true);

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        $id = $topic->id;
        $stat = $topic->purge();
        $this->assertTrue($stat);
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());
        $this->assertEquals($initial, $this->count_results($classname, true));
        $stat = $topic->purge();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, \midgard_connection::get_instance()->get_error());

        $topic = new $classname;
        $topic->name = __FUNCTION__ . ' 2';
        $topic->create();
        $this->assertEquals($id + 1, $topic->id);
    }

    public function test_get_parent()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->create();
        $child = new $classname;
        $child->up = $topic->id;
        $child->create();
        self::$em->clear();

        $child = self::$em->find('midgard:midgard_topic', $child->id);
        $parent = $child->get_parent();

        $this->assertInstanceOf($classname, $parent);
        $this->assertEquals($topic->guid, $parent->guid);

        $child->up = $topic->up;
        $child->update();
        self::$em->clear();

        $child = self::$em->find('midgard:midgard_topic', $child->id);
        $parent = $child->get_parent();

        $this->assertNull($parent);
    }

    public function test_childtype()
    {
        $topic_class = self::$ns . '\\midgard_topic';
        $article_class = self::$ns . '\\midgard_article';
        $topic = new $topic_class;
        $topic->create();

        $article = new $article_class;
        $this->assertFalse($article->create());
        $this->assertEquals(MGD_ERR_OBJECT_NO_PARENT, \midgard_connection::get_instance()->get_error());
        $article->topic = $topic->id;
        $this->assertTrue($article->create());
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());

        $this->assertEquals($topic->guid, $article->get_parent()->guid);
        $this->assertFalse($topic->delete());
        $this->assertEquals(MGD_ERR_HAS_DEPENDANTS, \midgard_connection::get_instance()->get_error());
        $this->assertTrue($article->delete());
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());
        $this->assertTrue($topic->delete());
    }

    public function test_uniquenames()
    {
        $classname = self::$ns . '\\midgard_snippetdir';
        $sd = new $classname;
        $sd->name = __FUNCTION__;
        $sd->create();

        $sd2 = new $classname;
        $sd2->name = __FUNCTION__;
        $stat = $sd2->create();
        $this->assertFalse($stat);

        $sd->delete();

        $stat = $sd2->create();
        $this->assertTrue($stat);

        $sd3 = new $classname;
        $sd3->name = __FUNCTION__;
        $stat = $sd3->create();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_OBJECT_NAME_EXISTS, \midgard_connection::get_instance()->get_error());

        $sd3->up = $sd2->id;
        $stat = $sd3->create();
        $this->assertTrue($stat);
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());

        //Empty names don't trigger duplicate error for some reason
        $sd4 = new $classname;
        $stat = $sd4->create();
        $this->assertTrue($stat);
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());

        $sd5 = new $classname;
        $stat = $sd5->create();
        $this->assertTrue($stat);
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());
    }

    public function test_get_by_path()
    {
        $classname = self::$ns . '\\midgard_snippetdir';
        $sd = new $classname;
        $sd->name = __FUNCTION__;
        $sd->create();

        $sd2 = new $classname;
        $sd2->up = $sd->id;
        $sd2->name = __FUNCTION__;
        $sd2->create();

        $x = new $classname;
        $this->assertTrue($x->get_by_path('/' . $sd->name));
        $this->assertEquals($sd->guid, $x->guid);
        $this->assertTrue($x->get_by_path('/' . $sd->name . '/' . $sd2->name));
        $this->assertEquals($sd2->guid, $x->guid);

        $this->assertFalse($x->get_by_path('/' . $sd->name . '/nonexistant'));
        $this->assertEquals('', $x->guid);
    }

    private function get_topic_with_parameter()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();

        $topic->set_parameter("midcom.core", "test", "some value");

        return $topic;
    }

    public function test_set_parameter()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();

        $stat = $topic->set_parameter("midcom.core", "test", "some value");

        $this->assertTrue($stat, "Failed to set parameter");

        // we should find a parameter with matching parent guid now
        $qb = new \midgard_query_builder(self::$ns . '\\midgard_parameter');
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $results = $qb->execute();
        $this->assertEquals(1, count($results), "Unable to find parameter");
    }

    public function test_parameter()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();

        $stat = $topic->parameter("midcom.core", "test", "some value");
        $this->assertTrue($stat, "Failed to set parameter");

        // we should find a parameter with matching parent guid now
        $qb = new \midgard_query_builder(self::$ns . '\\midgard_parameter');
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $results = $qb->execute();
        $this->assertEquals(1, count($results), "Unable to find parameter");

        $value = $topic->parameter("midcom.core", "test");
        $this->assertEquals('some value', $value);

        $stat = $topic->parameter("midcom.core", "test", null);
        $this->assertTrue($stat, "Failed to delete parameter");

        $qb = new \midgard_query_builder(self::$ns . '\\midgard_parameter');
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $results = $qb->execute();
        $this->assertEquals(0, count($results), "Parameter not deleted");
    }

    public function test_get_parameter()
    {
        // try retrieving parameter from non persistant object
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $value = $topic->get_parameter("midcom.core", "test");
        $this->assertFalse($value);

        $topic->create();

        // try getting value of non existant parameter
        $value = $topic->get_parameter("midcom.core", "nonexistant");
        $this->assertNull($value);

        // set count to 1
        $stat = $topic->set_parameter("midcom.core", "count", "1");
        $this->assertTrue($stat);

        // try getting parameter
        // value should be 1
        $value = $topic->get_parameter("midcom.core", "count");
        $this->assertEquals("1", $value);
        // there should be just one parameter
        $params = $topic->list_parameters();
        $this->assertEquals(1, count($params));

        // set count to 2
        // set the same parameter again, this should overwrite the old
        $stat = $topic->set_parameter("midcom.core", "count", "2");
        $this->assertTrue($stat);

        // try getting parameter
        // value should be 2 this time
        $value = $topic->get_parameter("midcom.core", "count");
        $this->assertEquals("2", $value);
        // there should still be just one parameter
        $params = $topic->list_parameters();
        $this->assertEquals(1, count($params));

        // set the same parameter again
        // this time use a false value
        // this should delete the parameter
        $stat = $topic->set_parameter("midcom.core", "count", false);
        $this->assertTrue($stat);

        $value = $topic->get_parameter("midcom.core", "count");
        $this->assertNull($value);
        // there should be no parameter left
        $params = $topic->list_parameters();
        $this->assertEquals(0, count($params));
    }

    public function test_has_parameters()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();

        $this->assertFalse($topic->has_parameters());

        $topic->set_parameter("midcom.core", "test", "some value");
        $this->assertTrue($topic->has_parameters());
    }

    public function test_list_parameters()
    {
        $topic = $this->get_topic_with_parameter();

        // add more parameters
        $topic->set_parameter("midcom.core", "test2", "other value");
        $topic->set_parameter("midcom.test", "test3", "another value");

        $params = $topic->list_parameters("false.domain");
        $this->assertEquals(0, count($params));

        // dont specify domain => get all
        $params = $topic->list_parameters();
        $this->assertEquals(3, count($params));

        $params = $topic->list_parameters("midcom.core");
        $this->assertEquals(2, count($params));

        $params = $topic->list_parameters("midcom.test");
        $this->assertEquals(1, count($params));

        // verify that we received the correct parameter
        $param = array_pop($params);
        $this->assertEquals("midcom.test", $param->domain);
        $this->assertEquals($topic->guid, $param->parentguid);
        $this->assertEquals("test3", $param->name);
        $this->assertEquals("another value", $param->value);
    }

    public function test_find_parameters()
    {
        $topic = $this->get_topic_with_parameter();

        // add another parameter
        $topic->set_parameter("midcom.test", "test3", "another value");

        // find all
        $params = $topic->find_parameters();
        $this->assertEquals(2, count($params));

        // find for midcom.core domain only
        $constraints = array();
        $constraints[] = array("domain", "=", "midcom.core");
        $params = $topic->find_parameters($constraints);
        $this->assertEquals(1, count($params));
    }

    public function test_delete_parameters()
    {
        $topic = $this->get_topic_with_parameter();
        $topic->set_parameter("midcom.test", "test3", "another value");

        $params = $topic->find_parameters();
        $this->assertEquals(2, count($params));

        // use constraint so no params get deleted
        $constraints = array();
        $constraints[] = array("domain", "=", "false.domain");
        $count = $topic->delete_parameters($constraints);
        $this->assertEquals(0, $count);

        // delete only core params
        $constraints = array();
        $constraints[] = array("domain", "=", "midcom.core");
        $count = $topic->delete_parameters($constraints);
        $this->assertEquals(1, $count);

        // now we should only find one parameter
        $params = $topic->find_parameters();
        $this->assertEquals(1, count($params));

        // we should find the deleted parameter if we include deleted
        $qb = new \midgard_query_builder(self::$ns . '\\midgard_parameter');
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $qb->add_constraint('domain', '=', "midcom.core");
        $qb->include_deleted();
        $results = $qb->execute();
        $this->assertEquals(1, count($results), "Unable to find parameter");
    }

    public function test_purge_parameters()
    {
        $topic = $this->get_topic_with_parameter();
        $topic->set_parameter("midcom.test", "test3", "another value");

        $params = $topic->find_parameters();
        $this->assertEquals(2, count($params));

        // use constraint so no params get deleted
        $constraints = array();
        $constraints[] = array("domain", "=", "false.domain");
        $count = $topic->purge_parameters($constraints);
        $this->assertEquals(0, $count);

        // purge only core params
        $constraints = array();
        $constraints[] = array("domain", "=", "midcom.core");
        $count = $topic->purge_parameters($constraints);
        $this->assertEquals(1, $count);

        // now we should only find one parameter
        $params = $topic->find_parameters();
        $this->assertEquals(1, count($params));

        // we should not even find the deleted parameter if we include deleted
        $qb = new \midgard_query_builder(self::$ns . '\\midgard_parameter');
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $qb->add_constraint('domain', '=', "midcom.core");
        $qb->include_deleted();
        $results = $qb->execute();
        $this->assertEquals(0, count($results), "Found a purged parameter");
    }


}
