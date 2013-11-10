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

    public function read_content()
    {
        if ($this->exists())
        {
            return file_get_contents($this->get_path());
        }
        return null;
    }

    public function write_content($content)
    {

    }

    public function remove_file()
    {

    }

    public function get_handler($mode = 'w')
    {
        return fopen($this->get_path(), $mode);
    }

    public function get_path()
    {
        if (empty($this->attachment->guid))
        {
            $this->attachment->set_guid(connection::generate_guid());
        }
        $blobdir = midgard_connection::get_instance()->config->blobdir;
        $subdir1 = substr($this->attachment->guid, 0, 1);
        $subdir2 = substr($this->attachment->guid, 1, 1);
        return $blobdir . '/' . strtoupper($subdir1) . '/' . strtoupper($subdir2) . '/' . $this->attachment->guid;
    }

    public function exists()
    {
        return file_exists($this->get_path());
    }
}