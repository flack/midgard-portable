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
        $classes = array(
            $factory->getMetadataFor('midgard:midgard_language'),
            $factory->getMetadataFor('midgard:midgard_topic'),
            $factory->getMetadataFor('midgard:midgard_article'),
            $factory->getMetadataFor('midgard:midgard_snippetdir'),
            $factory->getMetadataFor('midgard:midgard_repligard'),
        );
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

        $e = null;
        try
        {
            $loaded = new $classname($topic->id);
        }
        catch ( \midgard_error_exception $e){}

        $this->assertInstanceOf('midgard_error_exception', $e);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, \midgard_connection::get_instance()->get_error());
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
        $loaded->get_by_id($topic->id);
        $this->assertEquals($topic->id, $loaded->id);
        $this->assertEquals($topic->name, $loaded->name);
    }

    public function test_get_by_guid()
    {
        $classname = self::$ns . '\\midgard_topic';
        $topic = new $classname;
        $topic->name = __FUNCTION__;
        $topic->create();
        self::$em->clear();

        $loaded = new $classname;
        $loaded->get_by_guid($topic->guid);
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
        $stat = $topic->purge();
        $this->assertTrue($stat);
        $this->assertEquals(MGD_ERR_OK, \midgard_connection::get_instance()->get_error());
        $this->assertEquals($initial, $this->count_results($classname, true));
        $stat = $topic->purge();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_NOT_EXISTS, \midgard_connection::get_instance()->get_error());
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
        $article->topic = $topic->id;
        $this->assertTrue($article->create());

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

        $sd3->up = $sd2->id;
        $stat = $sd3->create();
        $this->assertTrue($stat);
    }
}
