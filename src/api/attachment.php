<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\api;

class attachment extends mgdobject
{
    private $id = 0;
    protected $guid = '';

    protected $name = '';
    protected $title = '';
    protected $location = '';
    protected $mimetype = '';
    protected $parentguid = '';

    public function create()
    {
        if (empty($this->parentguid)) {
            return false;
        }
        return parent::create();
    }
}
