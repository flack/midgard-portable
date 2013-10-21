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
        self::$_topic->name = __FUNCTION__;
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

    public function test_get()
    {
        $classname = self::$ns . '\\midgard_topic';

        $mc = new \midgard_collector($classname, 'id', self::$_topic->id);
        $mc->execute();
        $keys = $mc->list_keys();

        // try getting an invalid key
        $data = $mc->get('hello');
        $this->assertFalse($data);

        // try getting a valid key
        $data = $mc->get(key($keys));

        $this->assertEquals($data["id"], self::$_topic->id);
        $this->assertEquals($data["name"], self::$_topic->name);
    }

    public function test_get_subkey()
    {
        $classname = self::$ns . '\\midgard_topic';

        $mc = new \midgard_collector($classname, 'id', self::$_topic->id);
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