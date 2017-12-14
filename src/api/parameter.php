<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\api;

class parameter extends mgdobject
{
    private $id = 0;
    protected $guid = '';

    protected $domain = '';
    protected $name = '';
    protected $value = '';
    protected $parentguid = '';

    public function get_label()
    {
        return $this->domain . " " . $this->name;
    }
}
