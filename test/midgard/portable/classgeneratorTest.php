<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\classgenerator;
use midgard\portable\xmlreader;

class classgeneratorTest extends testcase
{
	private $directory;

	public function setUp()
	{
        parent::setUp();

        $this->directory = TESTDIR . '__output';
        if (is_dir($this->directory))
        {
            system("rm -rf " . escapeshellarg($this->directory));
        }
        mkdir($this->directory);
    }

    public function test_multiple()
    {
        // TODO: the setup is done implicitly by the parent class, we can't
        // do it individually in place right now, since the driver setup
        // is a bit too tangled
        $classname = self::$ns . '\\midgard_topic';
        $this->assertTrue(class_exists($classname));

        $topic = new $classname;

        $this->assertInstanceOf('midgard_dbobject', $topic);
        $this->assertInstanceOf('midgard_metadata', $topic->metadata);
        $this->assertInstanceOf('midgard_datetime', $topic->metadata->created);
        $this->assertInstanceOf('\\midgard\\portable\\storage\\metadata\\entity', $topic);
        $this->assertEquals(0, $topic->score);
    }
}