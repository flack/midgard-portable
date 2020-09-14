<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_connection;
use PHPUnit\Framework\TestCase;
use midgard\portable\test\testcase as mgdcase;

class connectionTest extends TestCase
{
    public function test_get_config()
    {
        $driver = mgdcase::prepare_connection('', null, uniqid(__CLASS__));
        $config = midgard_connection::get_instance()->config;
        $tmpdir = $driver->get_vardir();

        $this->assertInstanceOf('midgard_config', $config);
        $this->assertEquals($tmpdir, $config->vardir);
        $this->assertEquals($tmpdir . '/cache', $config->cachedir);
        $this->assertEquals($tmpdir . '/blobs', $config->blobdir);
        $this->assertEquals($tmpdir . '/schemas', $config->sharedir);
        $this->assertEquals($tmpdir . '/log/midgard-portable.log', $config->logfilename);
    }
}
