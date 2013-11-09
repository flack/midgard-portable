<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test\api;

use PHPUnit_Framework_TestCase;
use midgard_connection;

class connectionTest extends PHPUnit_Framework_TestCase
{
    public function test_set_error()
    {
        $connection = new midgard_connection;
        $this->assertEquals('MGD_ERR_OK', $connection->get_error_string());
        $connection->set_error(MGD_ERR_HAS_DEPENDANTS);
        $this->assertEquals('Object has dependants.', $connection->get_error_string());
    }
}