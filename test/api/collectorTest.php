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
            self::$em->getClassMetadata('midgard:midgard_article'),
            self::$em->getClassMetadata('midgard:midgard_user'),
            self::$em->getClassMetadata('midgard:midgard_person'),
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

    public function test_construct_with_association_id()
    {
        $topic_class = self::$ns . '\\midgard_topic';
        $article_class = self::$ns . '\\midgard_article';

        $topic = new $topic_class;
        $topic->name = __FUNCTION__;
        $topic->create();

        $article = new $article_class;
        $article->name = __FUNCTION__;
        $article->topic = $topic->id;
        $article->create();
        self::$em->clear();

        $mc = new \midgard_collector($article_class, 'topic', $topic->id);
        $mc->add_constraint('up', '=', $article->up);
        $mc->add_constraint('topic', '<>', 0);
        $mc->execute();
        $keys = $mc->list_keys();
        $this->assertEquals(1, count($keys));
        $this->assertEquals($article->guid, key($keys));
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

    public function test_set_key_property_guid_link()
    {
        $person_class = self::$ns . '\\midgard_person';
        $user_class = self::$ns . '\\midgard_user';

        $person = new $person_class;
        $person->create();

        $user = new $user_class;
        $user->authtype = 'Legacy';
        $user->set_person($person);
        $user->create();

        $mc = new \midgard_collector($user_class, 'id', $user->id);
        $mc->set_key_property('person');
        $mc->execute();
        $keys = $mc->list_keys();
        $this->assertEquals(1, count($keys));
        $this->assertEquals($person->guid, key($keys));
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

    public function test_aliased_fieldname()
    {
        $classname = self::$ns . '\\midgard_topic';
        $cm = self::$em->getClassMetadata($classname);
        $cm->midgard['field_aliases'] = array('id_alias' => 'id');

        $mc = new \midgard_collector($classname, 'id_alias', self::$_topic->id);
        $mc->add_value_property("id_alias");
        $mc->execute();
        $keys = $mc->list_keys();
        $key = key($keys);

        $data = $mc->get_subkey($key, 'id_alias');
        $this->assertEquals(self::$_topic->id, $data);
    }

}