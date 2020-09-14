<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test\api;

use midgard_connection;
use PHPUnit\Framework\TestCase;
use midgard\portable\api\config;

class connectionTest extends TestCase
{
    public function test_set_error()
    {
        $connection = new midgard_connection;
        $this->assertEquals('MGD_ERR_OK', $connection->get_error_string());
        $connection->set_error(MGD_ERR_HAS_DEPENDANTS);
        $this->assertEquals('Object has dependants.', $connection->get_error_string());
    }

    public function test_set_loglevel()
    {
        $connection = new midgard_connection;
        $this->assertTrue($connection->set_loglevel('error'));
        $this->assertEquals('error', $connection->get_loglevel());
        $this->assertFalse($connection->set_loglevel('x'));
        $this->assertEquals('error', $connection->get_loglevel());
    }

    public function test_open()
    {
        $connection = new midgard_connection;

        $connection = new midgard_connection;
        $this->assertFalse($connection->open('test'));
        $connection->open_config(new config);

        $this->assertFalse($connection->open('test'));
        $this->assertEquals(MGD_ERR_INTERNAL, $connection->get_error());
    }

    public function test_open_config()
    {
        $config = new config;
        $config->loglevel = 'message';
        $connection = new midgard_connection;
        $connection->open_config($config);

        $this->assertEquals('message', $connection->get_loglevel());
    }

    public function test_get_user()
    {
        $connection = new midgard_connection;
        $this->assertNull($connection->get_user());
    }
}
