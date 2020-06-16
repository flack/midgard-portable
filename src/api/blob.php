<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api;

use midgard\portable\storage\connection;
use midgard_connection;

class blob
{
    public $parentguid;

    public $content;

    protected $attachment;

    public function __construct(attachment $attachment, $encoding = 'UTF-8')
    {
        $this->attachment = $attachment;
    }

    public function read_content() : ?string
    {
        if ($this->exists()) {
            return file_get_contents($this->get_path());
        }
        return null;
    }

    public function write_content($content) : bool
    {
        return file_put_contents($this->get_path(), $content) !== false;
    }

    public function remove_file()
    {
    }

    public function get_handler($mode = 'w')
    {
        return fopen($this->get_path(), $mode);
    }

    public function get_path() : string
    {
        if (empty($this->attachment->location)) {
            $location = connection::generate_guid();
            $subdir1 = strtoupper($location[0]);
            $subdir2 = strtoupper($location[1]);
            $this->attachment->location = $subdir1 . DIRECTORY_SEPARATOR . $subdir2 . DIRECTORY_SEPARATOR . $location;
        }
        $blobdir = midgard_connection::get_instance()->config->blobdir;
        return $blobdir . DIRECTORY_SEPARATOR . $this->attachment->location;
    }

    public function exists() : bool
    {
        return file_exists($this->get_path());
    }
}
