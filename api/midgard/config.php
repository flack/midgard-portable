<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

class midgard_config
{
    public $dbtype = 'MySQL';
    public $database = 'midgard';
    public $port = 0;
    public $dbuser = '';
    public $dbpass = '';
    public $dbdir = '';
    public $host = 'localhost';
    public $logfilename = '';
    public $loglevel = 'warn';
    public $tablecreate = false;
    public $tableupdate = false;
    public $testunit = false;
    public $midgardusername = 'admin';
    public $midgardpassword = 'password';
    public $authtype = '';
    public $pamfile = '';
    public $blobdir = '/var/lib/midgard2/blobs';
    public $sharedir = '/usr/share/midgard2';
    public $vardir = '/var/lib/midgard2';
    public $cachedir = '/var/cache/midgard2';
    public $gdathreads = false;

	public function read_file_at_path($path)
	{

	}

	public function read_file($name, $user = true) // <== TODO: check
	{

	}

    public function save_file($name, $user = true) // <== TODO: check
    {

    }

    public function read_data($data)
    {

    }

    public static function list_files($user = true) // <== TODO: check
    {

    }

    public function create_blobdir()
    {

    }
}
?>
