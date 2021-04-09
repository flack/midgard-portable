<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\api;

/**
 * @property integer $id Local non-replication-safe database identifier
 * @property string $guid
 * @property string $firstname First name of the person
 * @property string $lastname Last name of the person
 */
class person extends mgdobject
{
    private $id = 0;

    protected $guid = '';

    protected $firstname = '';

    protected $lastname = '';
}
