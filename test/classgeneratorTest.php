<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;


class classgeneratorTest extends testcase
{
	private $directory;

	public function setUp()
	{
        $this->directory = TESTDIR . '__output';
        if (is_dir($this->directory))
        {
            system("rm -rf " . escapeshellarg($this->directory));
        }
        mkdir($this->directory);
    }

    public function test_standard()
    {
        $ns = uniqid(__CLASS__ . '__' . __FUNCTION__);
        self::prepare_connection('', $this->directory, $ns);

        $classname = $ns . '\\midgard_topic';
        $this->assertTrue(class_exists($classname));

        $topic = new $classname;

        $this->assertInstanceOf('midgard_dbobject', $topic);
        $this->assertInstanceOf('midgard_object', $topic);
        $this->assertInstanceOf('\\midgard\\portable\\api\\object', $topic);
        $this->assertInstanceOf($classname, $topic);
        $this->assertInstanceOf('midgard_metadata', $topic->metadata);
        $this->assertInstanceOf('midgard_datetime', $topic->metadata->created);
        $this->assertInstanceOf('\\midgard\\portable\\storage\\metadata\\entity', $topic);
        $this->assertEquals(0, $topic->score);
        $this->assertEquals('0001-01-01 00:00:00', $topic->metadata->created->format('Y-m-d H:i:s'));

        $cm = self::$em->getClassMetadata("midgard:midgard_parameter");
        $entity = $cm->newInstance();
        $entity->init();

        // without the default values in the mapping options, persisting the entity via em and flushing the em
        // would cause an integrity violation for all midgard_datetime fields (that cant be NULL)
        $this->assertInstanceOf('midgard_datetime', $entity->metadata->approved);
        $this->assertEquals('0001-01-01 00:00:00', $entity->metadata->approved->format('Y-m-d H:i:s'));
    }

    public function test_duplicate_tablenames()
    {
        $ns = uniqid(__CLASS__ . '__' . __FUNCTION__);
        self::prepare_connection('duplicate_tablenames/', $this->directory, $ns);

        $classname = $ns . '\\midgard_group';
        $this->assertTrue(class_exists($classname));

        $group = new $classname;

        $this->assertInstanceOf('midgard_dbobject', $group);
        $this->assertInstanceOf('midgard_object', $group);
        $this->assertInstanceOf('\\midgard\\portable\\api\\object', $group);
        $this->assertInstanceOf($classname, $group);
        // we can't test for non-namespaced classname, since some other test might have registered the alias already..
        //$this->assertInstanceOf('midgard_group', $group);
        $this->assertInstanceOf('midgard_metadata', $group->metadata);
        $this->assertInstanceOf('midgard_datetime', $group->metadata->created);
        $this->assertInstanceOf('\\midgard\\portable\\storage\\metadata\\entity', $group);
        $this->assertEquals(0, $group->owner);

        $org_classname = $ns . '\\org_openpsa_organization';
        $this->assertTrue(class_exists($org_classname));

        $org = new $org_classname;
        $this->assertInstanceOf($org_classname, $org);
        $this->assertInstanceOf($classname, $org);
        //$this->assertInstanceOf('org_openpsa_organization', $org);
        //$this->assertInstanceOf('midgard_group', $group);
        $this->assertEquals(0, $org->invoiceDue);
    }
}