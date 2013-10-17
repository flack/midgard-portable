<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\api;

use midgard_person;
use midgard\portable\api\object;

class person extends midgard_person
{
    private $id = 0;

    protected $guid = '';

    protected $firstname = '';

    protected $lastname = '';
}
?>
