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
    public function test_construct()
    {
        $config = new config;
        $this->assertEquals('midgard', $config->database);
    }
}