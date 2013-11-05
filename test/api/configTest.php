<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\api\config;

class configTest extends testcase
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

    public function test_construct()
    {
        $config = new config;
        $this->assertEquals('midgard', $config->database);
    }

    public function test_create_blobdir()
    {
        $config = new config;
        $config->blobdir = $this->directory;
        $this->assertTrue($config->create_blobdir());
        $this->assertTrue($config->create_blobdir());
        $this->assertFileExists($this->directory . '/E/0');
    }
}