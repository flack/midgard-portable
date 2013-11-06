<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api;

class config
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
        if (   !file_exists($path)
            || !is_readable($path))
        {
            return false;
        }
        $parsed = parse_ini_file($path);

        $this->apply_config($parsed);

        return true;
    }

    // TODO: find out if this could be moved to read_data()
    private function apply_config(array $config)
    {
        $mapping = array
        (
            'type' => 'dbtype',
            'username' => 'dbuser',
            'password' => 'dbpass',
            'databasedir' => 'dbdir',
        );

        foreach ($config as $key => $value)
        {
            $key = strtolower($key);
            if (array_key_exists($key, $mapping))
            {
                $key = $mapping[$key];
            }
            if (property_exists($this, $key))
            {
                if (is_bool($this->$key))
                {
                    $value = (boolean) $value;
                }
                $this->$key = $value;
            }
        }
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
        $subdirs = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F');
        foreach ($subdirs as $dir)
        {
            foreach ($subdirs as $subdir)
            {
                if (   !is_dir($this->blobdir . '/' . $dir . '/' . $subdir)
                    && !mkdir($this->blobdir . '/' . $dir . '/' . $subdir, 0777, true))
                {
                    return false;
                }
            }
        }

        return true;
    }
}