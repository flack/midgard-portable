<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;
use midgard\portable\storage\connection;
use midgard_connection;
use midgard\portable\api\error\exception;

class mgdobjectTest extends testcase
{
    public static function setupBeforeClass() : void
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
        $topic = $this->make_object('midgard_topic');
        $this->assertIsString($topic->name);
    }

    public function test_load()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;

        $this->assert_api('create', $topic);
        self::$em->clear();

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertEquals($topic->id, $loaded->id);
        $this->assertNotEquals('', $loaded->guid);
        $this->assertEquals($topic->name, $loaded->name);

        $loaded2 = $this->make_object('midgard_topic', $topic->guid);
        $this->assertEquals($topic->id, $loaded2->id);
        $this->assertEquals($topic->name, $loaded2->name);
    }

    public function test_load_deleted()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $this->assert_api('delete', $topic);

        self::$em->clear();
        $e = null;
        try {
            $this->make_object('midgard_topic', $topic->id);
        } catch (exception $e) {
        }

        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assert_error(MGD_ERR_NOT_EXISTS);
    }

    public function test_load_purged()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $this->assert_api('create', $topic);
        $id = $topic->id;
        $this->assert_api('delete', $topic);
        $this->assert_api('purge', $topic);

        $e = null;
        try {
            $this->make_object('midgard_topic', $id);
        } catch (exception $e) {
        }

        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, midgard_connection::get_instance()->get_error());

        if (extension_loaded('xdebug') && PHP_VERSION_ID >= 80000) {
            $this->markTestIncomplete('Workaround for https://bugs.xdebug.org/view.php?id=2100');
        }

        $e = null;
        try {
            $proxy = self::$em->getReference($classname, $id);
            new $classname($id);
        } catch (exception $e) {
        }

        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_OBJECT_PURGED, midgard_connection::get_instance()->get_error());
    }

    public function test_load_separate_instances()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();

        $topic->name = __FUNCTION__;

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertEquals('', $loaded->name);
    }

    public function test_load_invalid_guid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->make_object('midgard_topic', 'xxx');
    }

    public function test_get_by_id()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        self::$em->clear();

        $loaded = $this->make_object('midgard_topic');
        $stat = $loaded->get_by_id($topic->id);
        $this->assertTrue($stat);
        $this->assertEquals($topic->id, $loaded->id);
        $this->assertEquals($topic->name, $loaded->name);

        self::$em->clear();
        // Getting the reference now means we will get a proxy later from get_by_id
        $ref = self::$em->getReference($classname, $topic->id);

        $loaded = $this->make_object('midgard_topic');
        $stat = $loaded->get_by_id($topic->id);
        $this->assertTrue($stat);
        $this->assertEquals($topic->id, $loaded->id);
        $this->assertEquals($topic->name, $loaded->name);
    }

    public function test_get_by_id_with_updates()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $loaded = $this->make_object('midgard_topic');
        $stat = $loaded->get_by_id($topic->id);
        $this->assertTrue($stat);
        $this->assertEquals($topic->name, $loaded->name);

        // This causes the entity to become registered in IdentityMap
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('id', '=', $topic->id);
        $qb->execute();

        $topic->name = __FUNCTION__ . '2';
        $topic->update();
        $name = $topic->name;

        //If we load from IdentityMap and not from DB, we will get the pre-update value
        $stat = $loaded->get_by_id($topic->id);
        $this->assertTrue($stat);
        $this->assertEquals($topic->name, $loaded->name);
    }

    public function test_load_unknown_id()
    {
        $this->expectException(exception::class);
        $this->make_object('midgard_topic', 999999999);
    }

    public function test_get_by_guid()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic2 = $this->make_object('midgard_topic');
        $topic2->name = __FUNCTION__;
        $topic2->create();
        self::$em->clear();

        $loaded = $this->make_object('midgard_topic');
        $stat = $loaded->get_by_guid($topic->guid);
        $this->assertTrue($stat);
        $this->assertSame($topic->id, $loaded->id);
        $this->assertSame($topic->name, $loaded->name);

        $topic2->get_by_guid($topic2->guid);
        $loaded->up = $topic2->id;

        $this->assert_api('update', $loaded);

        $topic2->delete();
        $topic2->purge();
        $loaded2 = $this->make_object('midgard_topic', $topic->guid);

        $stat = $loaded2->get_by_guid($topic->guid);
        $this->assertTrue($stat);
        $this->assertEquals($topic->id, $loaded2->id);
        $this->assertEquals($topic2->id, $loaded2->up);
        $this->assertTrue($loaded2->delete());
        $this->assertTrue($loaded2->purge());
    }

    public function test_create()
    {
        $initial = $this->count_results('midgard_topic');

        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;

        $this->assert_api('create', $topic);
        $this->assertFalse(empty($topic->guid), 'GUID empty');
        $this->assertEquals($initial + 1, $this->count_results('midgard_topic'));
        $this->assertGreaterThan($initial, $topic->id);

        $topic2 = $this->make_object('midgard_topic');
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__ . '-2';
        $stat = $topic2->create();
        $this->assertTrue($stat);
        $this->assert_api('create', $topic2, MGD_ERR_DUPLICATE);
        $this->assertEquals($initial + 2, $this->count_results('midgard_topic'));
        $this->assertEquals($topic->id + 1, $topic2->id);

        $topic3 = $this->make_object('midgard_topic');
        $topic3->up = $topic->id;
        $topic3->name = __FUNCTION__ . '-3';
        $stat = $topic3->create();
        $this->assertTrue($stat);
    }

    public function test_create_duplicate_parentfield()
    {
        $sd = $this->make_object('midgard_snippetdir');
        $sd->name = __FUNCTION__;
        $this->assert_api('create', $sd);

        $sd2 = $this->make_object('midgard_snippetdir');
        $sd2->name = __FUNCTION__ . '2';
        $this->assert_api('create', $sd2);

        $sn = $this->make_object('midgard_snippet');
        $sn->name = 'dummy';
        $sn->snippetdir = $sd->id;
        $this->assert_api('create', $sn);

        $sn2 = $this->make_object('midgard_snippet');
        $sn2->name = 'dummy';
        $sn2->snippetdir = $sd2->id;
        $this->assert_api('create', $sn2);
    }

    public function test_create_invalid_guid_field()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->guidField = 'xx';

        $this->assert_api('create', $topic, MGD_ERR_INVALID_PROPERTY_VALUE);
    }

    public function test_update()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        $topic2 = $this->make_object('midgard_topic');
        $topic2->create();

        $topic->name = __FUNCTION__ . 'xxx';
        $topic->up = $topic2->id;
        $stat = $topic->update();
        $this->assertTrue($stat);
        self::$em->clear();

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertEquals($topic->name, $loaded->name);
        $this->assertEquals($topic2->id, $loaded->up, 'Wrong up ID');
        $this->assertEquals('', $loaded->title);
    }

    public function test_update_invalid_guid_field()
    {
        $topic = $this->make_object('midgard_topic');
        $this->assert_api('create', $topic);
        $topic->guidField = 'xx';

        $this->assert_api('update', $topic, MGD_ERR_INVALID_PROPERTY_VALUE);
    }

    public function test_update_circular_parent()
    {
        $topic = $this->make_object('midgard_topic');
        $this->assert_api('create', $topic);
        $topic->up = $topic->id;

        $this->assert_api('update', $topic, MGD_ERR_TREE_IS_CIRCULAR);
    }

    public function test_update_nonpersistent()
    {
        $topic = $this->make_object('midgard_topic');
        $this->assert_api('update', $topic, MGD_ERR_INTERNAL);
    }

    public function test_delete()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $initial = $this->count_results('midgard_topic');
        $initial_all = $this->count_results('midgard_topic', true);

        $topic = $this->make_object('midgard_topic');
        $name = uniqid(__FUNCTION__);
        $topic->name = $name;
        $topic->create();
        $topic->name = uniqid(__FUNCTION__ . time());
        $stat = $topic->delete();
        $this->assertTrue($stat);
        $this->verify_unpersisted_changes('midgard_topic', $topic->guid, "name", $name);
        $this->assertEquals($initial, $this->count_results('midgard_topic'));

        $all = $this->count_results('midgard_topic', true);
        $this->assertEquals($initial_all + 1, $all);

        // delete a topic that is already deleted
        $this->assert_api('delete', $topic);

        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('guid', '=', $topic->guid);
        $topic = self::$em->getReference($classname, $topic->id);

        $result = $qb->execute();
        $this->assertCount(1, $result);
        $this->assert_api('delete', $result[0]);
    }

    public function test_delete_nonpersistent()
    {
        $topic = $this->make_object('midgard_topic');
        $this->assert_api('delete', $topic, MGD_ERR_INVALID_PROPERTY_VALUE);
    }

    public function test_undelete()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $topic = $this->make_object('midgard_topic');
        $this->assert_api('create', $topic);
        $this->assert_api('delete', $topic);

        $stat = call_user_func_array($classname . "::undelete", [$topic->guid]);
        $this->assertTrue($stat);
        $refreshed = $this->make_object('midgard_topic', $topic->id);
        $this->assertFalse($refreshed->metadata->deleted);
    }

    public function test_list()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic2 = $this->make_object('midgard_topic');
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__;
        $topic2->create();

        $this->assertEquals([], $topic2->list());
        $children = $topic->list();
        $this->assertIsArray($children);
        $this->assertCount(1, $children);
        $this->assertSame($topic2->id, $children[0]->id);
    }

    public function test_has_dependents()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic2 = $this->make_object('midgard_topic');
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__;
        $topic2->create();

        $this->assertTrue($topic->has_dependents());
        $this->assert_api('delete', $topic2);
        $this->assertFalse($topic->has_dependents());
    }

    public function test_delete_with_dependents()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic2 = $this->make_object('midgard_topic');
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__;
        $topic2->create();

        $this->assert_api('delete', $topic, MGD_ERR_HAS_DEPENDANTS);
        $this->assert_api('delete', $topic2);
        $this->assert_api('delete', $topic);
    }

    public function test_purge_with_dependents()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $topic2 = $this->make_object('midgard_topic');
        $topic2->up = $topic->id;
        $topic2->name = __FUNCTION__;
        $topic2->create();

        $this->assert_api('purge', $topic, MGD_ERR_HAS_DEPENDANTS);
        $this->assert_api('delete', $topic2);
        $this->assert_api('purge', $topic);
    }

    public function test_purge()
    {
        $initial = $this->count_results('midgard_topic', true);

        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $id = $topic->id;
        $this->assert_api('purge', $topic);
        $this->assertEquals($initial, $this->count_results('midgard_topic', true));
        $this->assert_api('purge', $topic, MGD_ERR_NOT_EXISTS);

        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__ . ' 2';
        $topic->create();
        $this->assertEquals($id + 1, $topic->id);
    }

    public function test_get_parent()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        $child = $this->make_object('midgard_topic');
        $child->up = $topic->id;
        $child->create();
        self::$em->clear();

        $child = self::$em->find($classname, $child->id);
        $parent = $child->get_parent();

        $this->assertInstanceOf($classname, $parent);
        $this->assertEquals($topic->guid, $parent->guid);

        $child->up = $topic->up;
        $this->assert_api('update', $child);

        self::$em->clear();

        $child = self::$em->find($classname, $child->id);
        $parent = $child->get_parent();

        $this->assertNull($parent);
    }

    public function test_childtype()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();

        $article = $this->make_object('midgard_article');
        $this->assert_api('create', $article, MGD_ERR_OBJECT_NO_PARENT);
        $article->topic = $topic->id;
        $this->assert_api('create', $article);

        $this->assertEquals($topic->guid, $article->get_parent()->guid);
        $this->assert_api('delete', $topic, MGD_ERR_HAS_DEPENDANTS);
        $this->assert_api('delete', $article);
        $this->assert_api('delete', $topic);
    }

    /**
     * These are more or less random operations to provoke proxy usage in Doctrine
     */
    public function test_associations()
    {
        $sd = $this->make_object('midgard_snippetdir');
        $sd->name = __FUNCTION__;
        $this->assert_api('create', $sd);

        $sn = $this->make_object('midgard_snippet');
        $sn->name = __FUNCTION__;
        $sn->snippetdir = $sd->id;
        //This somehow causes the snippetdir reference oid to become stale
        $sd->get_by_id($sd->id);
        $sd->get_by_guid($sd->guid);

        $this->assert_api('create', $sn);

        $sd2 = $this->make_object('midgard_snippetdir');
        $sd2->name = __FUNCTION__ . '2';
        $this->assert_api('create', $sd2);

        $sn->snippetdir = $sd2->id;
        $this->assert_api('update', $sn);
        $this->assertTrue($sd2->has_dependents());

        $this->assert_api('delete', $sd2, MGD_ERR_HAS_DEPENDANTS);
        $sd2->purge(false);

        $sn->snippetdir = $sd->id;
        $this->assert_api('update', $sn);
        $sd->get_by_id($sd->id);
        $sd->name  = __FUNCTION__ . '1';
        $this->assert_api('update', $sd);

        $this->assertSame($sd->id, $sn->get_parent()->id);
        $this->assertSame(__FUNCTION__ . '1', $sn->get_parent()->name);

        $this->assert_api('delete', $sn);
        $this->assert_api('purge', $sn);
    }

    public function test_association_purge()
    {
        $sd = $this->make_object('midgard_snippetdir');
        $sd->name = __FUNCTION__;
        $this->assert_api('create', $sd);

        $sn = $this->make_object('midgard_snippet');
        $sn->name = __FUNCTION__;
        $sn->snippetdir = $sd->id;
        $this->assert_api('create', $sn);
        $this->assert_api('delete', $sn);
        $sd->get_by_guid($sd->guid);
        $sn->snippetdir = $sd->id;
        $this->assert_api('purge', $sn);
    }

    public function test_parent_purge()
    {
        $sd = $this->make_object('midgard_snippetdir');
        $sd->name = __FUNCTION__;
        $this->assert_api('create', $sd);

        $sd2 = $this->make_object('midgard_snippetdir');
        $sd2->name = __FUNCTION__ . '2';
        $sd2->up = $sd->id;
        $this->assert_api('create', $sd2);
        $this->assert_api('delete', $sd2);
        $this->assert_api('delete', $sd);
        $this->assert_api('purge', $sd); //this line would fail on InnoDB because of foreign key constraint violations
    }

    public function test_uniquenames()
    {
        $sd = $this->make_object('midgard_snippetdir');
        $sd->name = __FUNCTION__;
        $sd->create();

        $sd2 = $this->make_object('midgard_snippetdir');
        $sd2->name = __FUNCTION__;
        $stat = $sd2->create();
        $this->assertFalse($stat, midgard_connection::get_instance()->get_error_string());

        $sd->delete();

        $this->assert_api('create', $sd2);

        $sd3 = $this->make_object('midgard_snippetdir');
        $sd3->name = __FUNCTION__;
        $this->assert_api('create', $sd3, MGD_ERR_OBJECT_NAME_EXISTS);

        $sd3->up = $sd2->id;
        $this->assert_api('create', $sd3);

        //Empty names don't trigger duplicate error for some reason
        $sd4 = $this->make_object('midgard_snippetdir');
        $this->assert_api('create', $sd4);

        $sd5 = $this->make_object('midgard_snippetdir');
        $this->assert_api('create', $sd5);
    }

    public function test_get_by_path()
    {
        $sd = $this->make_object('midgard_snippetdir');
        $sd->name = __FUNCTION__;
        $sd->create();

        $sd2 = $this->make_object('midgard_snippetdir');
        $sd2->up = $sd->id;
        $sd2->name = __FUNCTION__;
        $sd2->create();

        $sn = $this->make_object('midgard_snippet');
        $sn->snippetdir = $sd->id;
        $sn->name = __FUNCTION__ . '-snippet';
        $sn->create();

        $x = $this->make_object('midgard_snippetdir');
        $this->assertTrue($x->get_by_path('/' . $sd->name));
        $this->assertEquals($sd->guid, $x->guid);
        $this->assertTrue($x->get_by_path('/' . $sd->name . '/' . $sd2->name));
        $this->assertEquals($sd2->guid, $x->guid);

        $this->assertFalse($x->get_by_path('/' . $sd->name . '/nonexistent'));
        $this->assertEquals('', $x->guid);

        $this->assertFalse($x->get_by_path('/' . $sd->name . '-notavailable/nonexistent'));
        $this->assertEquals('', $x->guid);

        $x = $this->make_object('midgard_snippet');
        $this->assertTrue($x->get_by_path('/' . $sd->name . '/' . $sn->name));
        $this->assertEquals($sn->guid, $x->guid);
    }

    private function get_topic_with_parameter()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();

        $topic->set_parameter("midcom.core", "test", "some value");

        return $topic;
    }

    public function test_set_parameter()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();

        $stat = $topic->set_parameter("midcom.core", "test", "some value");

        $this->assertTrue($stat, "Failed to set parameter");

        // we should find a parameter with matching parent guid now
        $qb = new \midgard_query_builder(connection::get_fqcn('midgard_parameter'));
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $results = $qb->execute();
        $this->assertCount(1, $results, "Unable to find parameter");
    }

    public function test_parameter()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();

        $stat = $topic->parameter("midcom.core", "test", "some value");
        $this->assertTrue($stat, "Failed to set parameter");

        // we should find a parameter with matching parent guid now
        $qb = new \midgard_query_builder(connection::get_fqcn('midgard_parameter'));
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $results = $qb->execute();
        $this->assertCount(1, $results, "Unable to find parameter");

        $value = $topic->parameter("midcom.core", "test");
        $this->assertEquals('some value', $value);

        $stat = $topic->parameter("midcom.core", "test", null);
        $this->assertTrue($stat, "Failed to delete parameter");

        $qb = new \midgard_query_builder(connection::get_fqcn('midgard_parameter'));
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $results = $qb->execute();
        $this->assertCount(0, $results, "Parameter not deleted");
    }

    public function test_get_parameter()
    {
        // try retrieving parameter from non persistent object
        $topic = $this->make_object('midgard_topic');
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
        $this->assertCount(1, $params);

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
        $this->assertCount(1, $params);

        // set the same parameter again
        // this time use a false value
        // this should delete the parameter
        $stat = $topic->set_parameter("midcom.core", "count", false);
        $this->assertTrue($stat);

        $value = $topic->get_parameter("midcom.core", "count");
        $this->assertNull($value);
        // there should be no parameter left
        $params = $topic->list_parameters();
        $this->assertCount(0, $params);

        // delete attempt on nonexistant parameter should return false
        $stat = $topic->set_parameter("midcom.core", "count", false);
        $this->assertFalse($stat, 'Delete nonexistant parameter should return false');
        $this->assertEquals(MGD_ERR_NOT_EXISTS, midgard_connection::get_instance()->get_error(), 'Unexpected status: ' . midgard_connection::get_instance()->get_error_string());
    }

    public function test_has_parameters()
    {
        $topic = $this->make_object('midgard_topic');
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
        $this->assertCount(0, $params);

        // don't specify domain => get all
        $params = $topic->list_parameters();
        $this->assertCount(3, $params);

        $params = $topic->list_parameters("midcom.core");
        $this->assertCount(2, $params);

        $params = $topic->list_parameters("midcom.test");
        $this->assertCount(1, $params);

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
        $this->assertCount(2, $params);

        // find for midcom.core domain only
        $constraints = [
            "domain" => "midcom.core"
        ];
        $params = $topic->find_parameters($constraints);
        $this->assertCount(1, $params);
    }

    public function test_delete_parameters()
    {
        $topic = $this->get_topic_with_parameter();
        $topic->set_parameter("midcom.test", "test3", "another value");

        $params = $topic->find_parameters();
        $this->assertCount(2, $params);

        // use constraint so no params get deleted
        $constraints = [
            "domain" => "false.domain"
        ];
        $count = $topic->delete_parameters($constraints);
        $this->assertEquals(0, $count);

        // delete only core params
        $constraints = [
            "domain" => "midcom.core"
        ];
        $count = $topic->delete_parameters($constraints);
        $this->assertEquals(1, $count);

        // now we should only find one parameter
        $params = $topic->find_parameters();
        $this->assertCount(1, $params);

        // we should find the deleted parameter if we include deleted
        $qb = new \midgard_query_builder(connection::get_fqcn('midgard_parameter'));
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $qb->add_constraint('domain', '=', "midcom.core");
        $qb->include_deleted();
        $results = $qb->execute();
        $this->assertCount(1, $results, "Unable to find parameter");
    }

    public function test_purge_parameters()
    {
        $topic = $this->get_topic_with_parameter();
        $topic->set_parameter("midcom.test", "test3", "another value");

        $params = $topic->find_parameters();
        $this->assertCount(2, $params);

        // use constraint so no params get deleted
        $constraints = [
            "domain" => "false.domain"
        ];
        $count = $topic->purge_parameters($constraints);
        $this->assertEquals(0, $count);

        // purge only core params
        $constraints = [
            "domain" => "midcom.core"
        ];
        $count = $topic->purge_parameters($constraints);
        $this->assertEquals(1, $count);

        // now we should only find one parameter
        $params = $topic->find_parameters();
        $this->assertCount(1, $params);

        // we should not even find the deleted parameter if we include deleted
        $qb = new \midgard_query_builder(connection::get_fqcn('midgard_parameter'));
        $qb->add_constraint('parentguid', '=', $topic->guid);
        $qb->add_constraint('domain', '=', "midcom.core");
        $qb->include_deleted();
        $results = $qb->execute();
        $this->assertCount(0, $results, "Found a purged parameter");
    }

    public function test_new_collector()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $mc = $classname::new_collector('id', 1);
        $this->assertInstanceOf('midgard_collector', $mc);
    }

    public function test_new_query_builder()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $qb = $classname::new_query_builder();
        $this->assertInstanceOf('midgard_query_builder', $qb);
    }

    public function test_set_guid()
    {
        $guid = connection::generate_guid();
        $topic = $this->make_object('midgard_topic');
        $topic->guid = $guid;
        $this->assertEquals('', $topic->guid);
        $topic->set_guid($guid);
        $this->assertEquals($guid, $topic->guid);
        $this->assertTrue($topic->create());
        $this->assertEquals($guid, $topic->guid);
    }

    public function test_lock()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        $topic->title = 'This should not be saved'; // change AFTER create
        connection::set_user(null);

        $this->assert_api('lock', $topic, MGD_ERR_ACCESS_DENIED);

        $person = self::create_user();

        $this->assert_api('lock', $topic);
        $this->assertTrue($topic->is_locked());
        $this->assert_api('lock', $topic, MGD_ERR_OBJECT_IS_LOCKED);
        $this->assertEquals($person->guid, $topic->metadata->locker);
        $this->assertEquals(0, $topic->metadata->revision);

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertTrue($loaded->is_locked());
        $this->assertEquals($person->guid, $loaded->metadata->locker);
        $this->verify_unpersisted_changes('midgard_topic', $topic->guid, "title", "");
    }

    public function test_unlock()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        connection::set_user(null);

        $this->assertFalse($topic->unlock());

        self::create_user();

        $this->assertFalse($topic->unlock());
        $this->assertTrue($topic->lock());
        $locker = $topic->metadata_locker;
        $locked = $topic->metadata_locked;
        $this->assertTrue($topic->is_locked());
        $this->assertTrue($topic->unlock());
        $this->assertFalse($topic->is_locked());
        $this->assertFalse($topic->unlock());

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertFalse($loaded->is_locked());
        $this->assertEquals($locker, $loaded->metadata->locker);
        $this->assertEquals($locked, $loaded->metadata->locked);
        $this->assertEquals(0, $topic->metadata->revision);
    }

    public function test_approve()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        $topic->title = 'This should not be saved'; // change AFTER create
        connection::set_user(null);

        $this->assertFalse($topic->approve());

        $person = self::create_user();

        $topic = $this->make_object('midgard_topic', $topic->id);
        $this->assertTrue($topic->approve());
        $this->assertTrue($topic->is_approved());
        $this->assertFalse($topic->approve());
        $this->assertEquals($person->guid, $topic->metadata->approver);

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertTrue($loaded->is_approved());
        $this->assertEquals($person->guid, $loaded->metadata->approver);
        $this->verify_unpersisted_changes('midgard_topic', $topic->guid, "title", "");
    }

    public function test_unapprove()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->create();
        connection::set_user(null);

        $this->assertFalse($topic->unapprove());

        self::create_user();

        $this->assertFalse($topic->unapprove());
        $this->assertTrue($topic->approve());
        $this->assertTrue($topic->is_approved());
        $this->assertTrue($topic->unapprove());
        $this->assertFalse($topic->is_approved());
        $this->assertFalse($topic->unapprove());

        $loaded = $this->make_object('midgard_topic', $topic->id);
        $this->assertFalse($loaded->is_approved());
        $this->assertEquals($topic->metadata_approver, $loaded->metadata->approver);
        $this->assertEquals($topic->metadata_approved, $loaded->metadata->approved);
    }

    public function test__debugInfo()
    {
        $topic = $this->make_object('midgard_topic');

        $metadata = new \stdClass;
        $metadata->creator = $topic->metadata->creator;
        $metadata->created = $topic->metadata->created;
        $metadata->revisor = $topic->metadata->revisor;
        $metadata->revised = $topic->metadata->revised;
        $metadata->revision = $topic->metadata->revision;
        $metadata->locker = $topic->metadata->locker;
        $metadata->locked = $topic->metadata->locked;
        $metadata->approver = $topic->metadata->approver;
        $metadata->approved = $topic->metadata->approved;
        $metadata->owner = $topic->metadata->owner;
        $metadata->schedulestart = $topic->metadata->schedulestart;
        $metadata->scheduleend = $topic->metadata->scheduleend;
        $metadata->hidden = $topic->metadata->hidden;
        $metadata->navnoentry = $topic->metadata->navnoentry;
        $metadata->size = $topic->metadata->size;
        $metadata->score = $topic->metadata->score;
        $metadata->published= $topic->metadata->published;
        $metadata->imported = $topic->metadata->imported;
        $metadata->exported = $topic->metadata->exported;
        $metadata->deleted = $topic->metadata->deleted;
        $metadata->islocked = $topic->metadata->islocked;
        $metadata->isapproved = $topic->metadata->isapproved;
        $metadata->authors = $topic->metadata->authors;

        $expected = [
            'id' => $topic->id,
            'guid' => $topic->guid,
            'name' => $topic->name,
            'code' => $topic->code,
            'style' => $topic->style,
            'styleInherit' => $topic->styleInherit,
            'title' => $topic->title,
            'extra' => $topic->extra,
            'description' => $topic->description,
            'score' => $topic->score,
            'floatField' => $topic->floatField,
            'guidField' => $topic->guidField,
            'up' => $topic->up,
            'symlink' => $topic->symlink,
            'lang' => $topic->lang,
            'birthdate' => $topic->birthdate,
            'metadata' => $metadata,
            'component' => ''

        ];
        $this->assertEquals($expected, $topic->__debugInfo());
    }
}
