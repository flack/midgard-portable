<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\storage\connection;

class parameterTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();

        $classes = self::get_metadata([
            'midgard_topic',
            'midgard_parameter',
            'midgard_repligard',
        ]);
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_get_label()
    {
        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();

        $topic->set_parameter("midcom.core", "test", "some value");
        $params = $topic->list_parameters();
        $this->assertCount(1, $params);

        $param = array_shift($params);
        $this->assertEquals("midcom.core test", $param->get_label());
    }

    public function test_parameter_proxy()
    {
        $classname = connection::get_fqcn('midgard_topic');

        $topic = $this->make_object('midgard_topic');
        $topic->name = __FUNCTION__;
        $topic->create();

        $ref = self::$em->getReference($classname, $topic->id);

        $this->assertTrue($ref->parameter("midcom.core", "test", "some value"));
        $params = $topic->list_parameters();
        $this->assertCount(1, $params);

        $param = array_shift($params);
        $this->assertEquals("midcom.core test", $param->get_label());
    }
}
