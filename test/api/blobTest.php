<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\api\attachment;
use midgard\portable\api\blob;

class blobTest extends testcase
{

    public function test_construct()
    {
        $att = new attachment;
        $blob = new blob($att);
        $this->assertEquals('', $blob->content);
    }

}