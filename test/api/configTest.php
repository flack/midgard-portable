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
        if (is_dir($this->directory)) {
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

    public function test_read_file_at_path()
    {
        $config = new config;
        $this->assertTrue($config->read_file_at_path(TESTDIR . '__files/config/example'));

        $this->assertEquals('MySQL', $config->dbtype);
        $this->assertEquals('/tmp/blobs', $config->blobdir);
        $this->assertEquals('/tmp/dbdir', $config->dbdir);
        $this->assertTrue($config->tablecreate);
    }

    public function test_save_file()
    {
        $config = new config;
        $config->tablecreate = true;
        $config->database = 'test';
        $filename = uniqid(__FUNCTION__);
        $path = getenv('HOME') . '/.midgard2/conf.d/' . $filename;
        $this->assertTrue($config->save_file($filename));
        $this->assertFileExists($path);

        $this->assertTrue($config->read_file_at_path($path));
        $this->assertTrue($config->tablecreate);
        $this->assertEquals('test', $config->database);
        unlink($path);
    }
}
