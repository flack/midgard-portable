<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;


use midgard\portable\storage\connection;

class midgard_collectorTest extends testcase
{
    protected static $_topic;

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

        // create topic
        $classname = connection::get_fqcn('midgard_topic');
        self::$_topic = new $classname;
        self::$_topic->name = uniqid("cT_mainTopic");
        self::$_topic->title = uniqid("cT_mainTopic");
        self::$_topic->metadata_revision = 1;
        self::$_topic->create();
    }

    public function test_construct_with_association_id()
    {
        $article_class = connection::get_fqcn('midgard_article');

        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();

        $article = $this->make_object('midgard_article');
        $article->name = __FUNCTION__;
        $article->topic = $topic->id;
        $article->create();
        self::$em->clear();

        $mc = new \midgard_collector($article_class, 'topic', $topic->id);
        $mc->add_constraint('up', '=', $article->up);
        $mc->add_constraint('topic', '<>', 0);
        $mc->execute();
        $keys = $mc->list_keys();
        $this->assertCount(1, $keys);
        $this->assertEquals($article->guid, key($keys));
    }

    public function test_list_keys()
    {
        $classname = connection::get_fqcn('midgard_topic');

        // call without calling execute, this should return an empty array
        $mc = new \midgard_collector($classname, 'id', self::$_topic->id);
        $keys = $mc->list_keys();

        $this->assertCount(0, $keys);

        // call execute and try again, this time we should get the keys
        $mc->execute();
        $keys = $mc->list_keys();

        $this->assertCount(1, $keys);
        $this->assertTrue(array_key_exists(self::$_topic->guid, $keys));
    }

    public function test_add_value_property()
    {
        $classname = connection::get_fqcn('midgard_topic');

        // create child topic for main topic
        $child_topic = $this->make_object('midgard_topic');
        $child_topic->name = uniqid("cT_childTopic");
        $child_topic->title = uniqid("cT_childTopic");
        $child_topic->up = self::$_topic->id;
        $child_topic->metadata_revision = 4;
        $child_topic->create();

        // without adding a value property
        $mc = new \midgard_collector($classname, 'id', $child_topic->id);
        $mc->add_value_property("name");
        $mc->add_value_property("guid");
        $mc->add_value_property("metadata.revision");

        $mc->add_value_property("up");
        $mc->add_value_property("up.name");
        $mc->add_value_property("up.metadata.revision");
        $mc->execute();

        $keys = $mc->list_keys();
        $result = $mc->get(key($keys));

        // existing property
        $this->assertArrayHasKey("name", $result);
        $this->assertEquals($result["name"], $child_topic->name);

        // guid property
        $this->assertArrayHasKey("guid", $result);
        $this->assertEquals($result["guid"], $child_topic->guid);

        // non existing property
        $this->assertArrayNotHasKey("extra", $result);

        // metadata property
        $this->assertArrayHasKey("revision", $result);
        $this->assertEquals($result["revision"], $child_topic->metadata_revision);

        // join field
        $this->assertArrayHasKey("up", $result);
        $this->assertSame($result["up"], self::$_topic->id);

        // properties of the linked object
        // in midgard this would have totally messed up the results
        $this->assertArrayHasKey("up_name", $result);
        $this->assertEquals($result["up_name"], self::$_topic->name);

        // combined with metadata property
        $this->assertArrayHasKey("up_revision", $result);
        $this->assertEquals($result["up_revision"], self::$_topic->metadata_revision);
    }

    public function test_add_value_property_twice()
    {
        $classname = connection::get_fqcn('midgard_topic');

        $mc = new \midgard_collector($classname, 'up', 0);
        $this->assertTrue($mc->set_key_property("name"));
        $this->assertTrue($mc->add_value_property("name"));
        $this->assertTrue($mc->add_value_property("name"));
        $ref = new \ReflectionClass($mc);
        $properties = $ref->getProperty('value_properties');
        $properties->setAccessible(true);
        $this->assertCount(1, $properties->getValue($mc));
    }

    public function test_add_value_property_nonexistant()
    {
        $classname = connection::get_fqcn('midgard_topic');

        $mc = new \midgard_collector($classname, 'up', 0);
        $this->assertTrue($mc->set_key_property("name"));
        $this->assertFalse($mc->add_value_property("xxxx"));
        $this->assertFalse($mc->add_value_property("metadata.xxxx"));
        $this->assertFalse($mc->add_value_property("up.xxxx"));
        $ref = new \ReflectionClass($mc);
        $properties = $ref->getProperty('value_properties');
        $properties->setAccessible(true);
        $this->assertCount(0, $properties->getValue($mc));
    }

    public function test_set_key_property()
    {
        $classname = connection::get_fqcn('midgard_topic');

        // create child topic for main topic
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();
        $mc = new \midgard_collector($classname, 'id', $topic->id);
        $mc->set_key_property('id');
        $mc->execute();
        $keys = $mc->list_keys();
        $this->assertCount(1, $keys);
        $this->assertEquals($topic->id, key($keys));
    }

    public function test_set_key_property_guid_link()
    {
        $user_class = connection::get_fqcn('midgard_user');

        $person = $this->make_object('midgard_person');
        $person->create();

        $user = $this->make_object('midgard_user');
        $user->authtype = 'Legacy';
        $user->set_person($person);
        $user->create();

        $mc = new \midgard_collector($user_class, 'id', $user->id);
        $mc->set_key_property('person');
        $mc->execute();
        $keys = $mc->list_keys();
        $this->assertCount(1, $keys);
        $this->assertEquals($person->guid, key($keys));
    }

    public function test_get()
    {
        $classname = connection::get_fqcn('midgard_topic');

        $mc = new \midgard_collector($classname, 'id', self::$_topic->id);
        $mc->set_key_property("guid");
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
        $this->assertFalse(array_key_exists("guid", $result));
    }

    public function test_get_subkey()
    {
        $classname = connection::get_fqcn('midgard_topic');

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

    public function test_aliased_fieldname()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $cm = self::$em->getClassMetadata($classname);
        $cm->midgard['field_aliases'] = ['id_alias' => 'id'];

        $mc = new \midgard_collector($classname, 'id_alias', self::$_topic->id);
        $mc->add_value_property("id_alias");
        $mc->execute();
        $keys = $mc->list_keys();
        $key = key($keys);

        $data = $mc->get_subkey($key, 'id_alias');
        $this->assertEquals(self::$_topic->id, $data);
    }
}
