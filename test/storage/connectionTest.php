<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\driver;
use midgard\portable\storage\connection;
use midgard_connection;

class connectionTest extends \PHPUnit_Framework_TestCase
{
    public function test_get_config()
    {
        $directories = [TESTDIR . '__files/'];
        $tmpdir = sys_get_temp_dir();
        $ns = uniqid(__CLASS__);
        $driver = new driver($directories, $tmpdir, $ns);
        include TESTDIR . DIRECTORY_SEPARATOR . 'bootstrap.php';
        $em = connection::get_em();
        $config = midgard_connection::get_instance()->config;

        $this->assertInstanceOf('midgard_config', $config);
        $this->assertEquals($tmpdir, $config->vardir);
        $this->assertEquals($tmpdir . '/cache', $config->cachedir);
        $this->assertEquals($tmpdir . '/blobs', $config->blobdir);
        $this->assertEquals($tmpdir . '/schemas', $config->sharedir);
        $this->assertEquals($tmpdir . '/log/midgard-portable.log', $config->logfilename);
    }
}
