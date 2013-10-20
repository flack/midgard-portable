<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_object_class;

class classTest extends testcase
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = array(
            $factory->getMetadataFor('midgard:midgard_language'),
            $factory->getMetadataFor('midgard:midgard_topic'),
            $factory->getMetadataFor('midgard:midgard_repligard'),
        );
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_factory()
    {
        $classname = self::$ns . '\\midgard_topic';
        $object = midgard_object_class::factory('midgard_topic');
        $this->assertInstanceOf($classname, $object);

        $topic = new $classname;
        $topic->create();

        $object = midgard_object_class::factory('midgard_topic', $topic->id);
        $this->assertInstanceOf($classname, $object);

        $object = midgard_object_class::factory('midgard_topic', $topic->guid);
        $this->assertInstanceOf($classname, $object);
    }

    public function test_get_object_by_guid()
    {
        $classname = self::$ns . '\\midgard_topic';

        $topic = new $classname;
        $topic->create();

        $object = midgard_object_class::get_object_by_guid($topic->guid);
        $this->assertInstanceOf($classname, $object);
    }
}