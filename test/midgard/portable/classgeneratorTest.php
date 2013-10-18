<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\classgenerator;
use midgard\portable\mgdschema\manager;

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
        self::prepare_connection(array(TESTDIR . '__files/'), $this->directory, $ns);

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
    }

    public function test_duplicate_tablenames()
    {
        $ns = uniqid(__CLASS__ . '__' . __FUNCTION__);
        self::prepare_connection(array(TESTDIR . '__files/duplicate_tablenames/'), $this->directory, $ns);

        $classname = $ns . '\\midgard_group';
        $this->assertTrue(class_exists($classname));

        $group = new $classname;

        $this->assertInstanceOf('midgard_dbobject', $group);
        $this->assertInstanceOf('midgard_object', $group);
        $this->assertInstanceOf('\\midgard\\portable\\api\\object', $group);
        $this->assertInstanceOf($classname, $group);
        $this->assertInstanceOf('midgard_metadata', $group->metadata);
        $this->assertInstanceOf('midgard_datetime', $group->metadata->created);
        $this->assertInstanceOf('\\midgard\\portable\\storage\\metadata\\entity', $group);
        $this->assertEquals(0, $group->owner);

        $classname = $ns . '\\org_openpsa_organization';
        $this->assertTrue(class_exists($classname));

        $org = new $classname;
        $this->assertInstanceOf('midgard_dbobject', $org);
        $this->assertInstanceOf('midgard_object', $org);
        $this->assertInstanceOf('\\midgard\\portable\\api\\object', $org);
        $this->assertInstanceOf($classname, $org);
        $this->assertInstanceOf('midgard_metadata', $org->metadata);
        $this->assertInstanceOf('midgard_datetime', $org->metadata->created);
        $this->assertInstanceOf('\\midgard\\portable\\storage\\metadata\\entity', $org);
        $this->assertEquals(0, $org->invoiceDue);
    }
}