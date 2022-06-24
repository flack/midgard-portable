<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\api;

/**
 * @property integer $id Local non-replication-safe database identifier
 * @property string $name Filename of the attachment
 * @property string $title Title of the attachment
 * @property string $location Location of the attachment in the blob directory structure
 * @property string $mimetype MIME type of the attachment
 * @property string $parentguid GUID of the object the attachment is attached to
 * @property string $guid
 */
class attachment extends mgdobject
{
    protected $guid = '';

    protected $name = '';
    protected $title = '';
    protected $location = '';
    protected $mimetype = '';
    protected $parentguid = '';

    public function create() : bool
    {
        if (empty($this->parentguid)) {
            return false;
        }
        return parent::create();
    }
}
