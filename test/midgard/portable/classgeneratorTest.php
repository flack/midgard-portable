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
        self::$ns = uniqid(get_called_class());
        $this->directory = TESTDIR . '__output';
        if (is_dir($this->directory))
        {
            system("rm -rf " . escapeshellarg($this->directory));
        }
        mkdir($this->directory);
    }

    public function test_multiple()
    {
        $classname = self::$ns . '\\midgard_topic';
        $reader = new xmlreader;
        $types = $reader->parse(TESTDIR . '__files/midgard_topic.xml');
        $types = array_merge($types, $reader->parse(TESTDIR . '__files/midgard_snippetdir.xml'));

        $classgenerator = new classgenerator($this->directory . '/midgard_dbobjects.php');
        $classgenerator->write($types, array(), self::$ns);

        $this->assertFileExists($this->directory . '/midgard_dbobjects.php');
        include $this->directory . '/midgard_dbobjects.php';
        $this->assertTrue(class_exists('midgard_topic'));

        $topic = new $classname;

        $this->assertInstanceOf('midgard_dbobject', $topic);
        $this->assertInstanceOf('midgard_metadata', $topic->metadata);
        $this->assertInstanceOf('midgard_datetime', $topic->metadata->created);
        $this->assertInstanceOf('\\midgard\\portable\\storage\\metadata\\entity', $topic);
        $this->assertEquals(0, $topic->score);
    }
}