<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

class parameterTest extends testcase
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = array(
            $factory->getMetadataFor('midgard:midgard_topic'),
            $factory->getMetadataFor('midgard:midgard_parameter'),
            $factory->getMetadataFor('midgard:midgard_repligard'),
        );
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_get_label()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();

        $topic->set_parameter("midcom.core", "test", "some value");
        $params = $topic->list_parameters();
        $this->assertEquals(1, count($params));

        $param = array_shift($params);
        $this->assertEquals("midcom.core test", $param->get_label());
    }

    public function test_parameter_proxy()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();

        $ref = self::$em->getReference($classname, $topic->id);

        $this->assertTrue($ref->parameter("midcom.core", "test", "some value"));
        $params = $topic->list_parameters();
        $this->assertEquals(1, count($params));

        $param = array_shift($params);
        $this->assertEquals("midcom.core test", $param->get_label());
    }
}
