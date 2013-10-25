<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_dbobject;

class midgard_collectorTest extends testcase
{
    protected static $_topic;

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

        // create topic
        $classname = self::$ns . '\\midgard_topic';
        self::$_topic = new $classname;
        self::$_topic->name = uniqid("cT_mainTopic");
        self::$_topic->title = uniqid("cT_mainTopic");
        self::$_topic->metadata_revision = 1;
        self::$_topic->create();
    }

    public function test_list_keys()
    {
        $classname = self::$ns . '\\midgard_topic';

        // call without calling execute, this should return an empty array
        $mc = new \midgard_collector($classname, 'id', self::$_topic->id);
        $keys = $mc->list_keys();

        $this->assertEquals(0, count($keys));

        // call execute and try again, this time we should get the keys
        $mc->execute();
        $keys = $mc->list_keys();

        $this->assertEquals(1, count($keys));
        $this->assertTrue(array_key_exists(self::$_topic->guid, $keys));
    }

    public function test_add_value_property()
    {
        $classname = self::$ns . '\\midgard_topic';

        // create child topic for main topic
        $child_topic = new $classname;
        $child_topic->name = uniqid("cT_childTopic");
        $child_topic->title = uniqid("cT_childTopic");
        $child_topic->up = self::$_topic->id;
        $child_topic->metadata_revision = 4;
        $child_topic->create();

        // without adding a value property
        $mc = new \midgard_collector($classname, 'id', $child_topic->id);
        $mc->add_value_property("name");
        $mc->add_value_property("metadata.revision");

        $mc->add_value_property("up");
        $mc->add_value_property("up.name");
        $mc->add_value_property("up.metadata.revision");
        $mc->execute();

        $keys = $mc->list_keys();
        $result = $mc->get(key($keys));

        // existing property
        $this->assertTrue(array_key_exists("name", $result));
        $this->assertEquals($result["name"], $child_topic->name);

        // non existing property
        $this->assertFalse(array_key_exists("extra", $result));

        // metadata property
        $this->assertTrue(array_key_exists("revision", $result));
        $this->assertEquals($result["revision"], $child_topic->metadata_revision);

        // join field
        $this->assertTrue(array_key_exists("up", $result));
        $this->assertEquals($result["up"], self::$_topic->id);

        // properties of the linked object
        // in midgard this would have totally messed up the results
        $this->assertTrue(array_key_exists("up_name", $result));
        $this->assertEquals($result["up_name"], self::$_topic->name);

        // combined with metadata property
        $this->assertTrue(array_key_exists("up_revision", $result));
        $this->assertEquals($result["up_revision"], self::$_topic->metadata_revision);
    }

    public function test_set_key_property()
    {
        $classname = self::$ns . '\\midgard_topic';

        // create child topic for main topic
        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        $mc = new \midgard_collector($classname, 'id', $topic->id);
        $mc->set_key_property('id');
        $mc->execute();
        $keys = $mc->list_keys();
        $this->assertEquals(1, count($keys));
        $this->assertEquals($topic->id, key($keys));
    }

    public function test_get()
    {
        $classname = self::$ns . '\\midgard_topic';

        $mc = new \midgard_collector($classname, 'id', self::$_topic->id);
        $mc->add_value_property("name");
        $mc->add_value_property("id");
        $mc->execute();
        $keys = $mc->list_keys();

        // try getting an invalid key
        $result = $mc->get('hello');
        $this->assertFalse($result);

        // try getting a valid key
        $result = $mc->get(key($keys));

        $this->assertEquals($result["id"], self::$_topic->id);
        $this->assertEquals($result["name"], self::$_topic->name);
        // was not added as value property
        $this->assertFalse(array_key_exists("title", $result));
    }

    public function test_get_subkey()
    {
        $classname = self::$ns . '\\midgard_topic';

        $mc = new \midgard_collector($classname, 'id', self::$_topic->id);
        $mc->add_value_property("name");
        $mc->execute();
        $keys = $mc->list_keys();
        $key = key($keys);

        // try invalid key
        $data = $mc->get_subkey('hello', 'name');
        $this->assertFalse($data);

        // try valid key but invalid property
        $data = $mc->get_subkey($key, 'nowhereCC');
        $this->assertFalse($data);

        // try valid key & data
        $data = $mc->get_subkey($key, 'name');
        $this->assertEquals($data, self::$_topic->name);
    }

}