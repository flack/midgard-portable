<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\api;

/**
 * @property integer $id Local non-replication-safe database identifier
 * @property string $domain Namespace of the parameter
 * @property string $name Key of the parameter
 * @property string $value Value of the parameter
 * @property string $parentguid GUID of the object the parameter extends
 * @property string $guid
 */
class parameter extends mgdobject
{
    private int $id = 0;
    protected string $guid = '';

    protected $domain = '';
    protected $name = '';
    protected $value = '';
    protected $parentguid = '';

    public function get_label() : string
    {
        return $this->domain . " " . $this->name;
    }
}
