<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use Doctrine\ORM\UnitOfWork;
use midgard\portable\storage\connection;

class dbobject_links_as_entitiesTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        self::prepare_connection('links_as_entities/');

        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = $factory->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_get_empty_link_property()
    {
        $topic = $this->make_object('midgard_topic');
        $this->assertNull($topic->up);
    }

    public function test_set()
    {
        $topic = $this->make_object('midgard_topic');

        $this->assertNull($topic->lang);
        $this->assertNull($topic->up);

        $topic2 = $this->make_object('midgard_topic');
        $topic->up = $topic2;
        $this->assertSame($topic2, $topic->up);

        $lang = $this->make_object('midgard_language');
        $topic->lang = $lang;
        $this->assertSame($lang, $topic->lang);
    }

    public function test_get()
    {
        $classname = connection::get_fqcn('midgard_topic');
        $parent = $this->make_object('midgard_topic');
        $parent->name = __FUNCTION__;
        $parent->create();

        $topic = $this->make_object('midgard_topic');
        $topic->up = $parent;
        $topic->create();
        self::$em->clear();

        $qb = new \midgard_query_builder($classname);
        $qb->add_constraint('id', '=', $parent->id);
        $results = $qb->execute();

        $this->assertSame($topic->up->guid, $results[0]->guid);
    }
}
