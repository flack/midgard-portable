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
            'name' => 'database',
            'username' => 'dbuser',
            'password' => 'dbpass',
            'databasedir' => 'dbdir',
            'logfilename' => 'logfile',
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
        if (!$user)
        {
            throw new Exception('Not implemented');
        }
        $prefix = getenv('HOME') . '/.midgard2/conf.d';
        if (!file_exists($prefix))
        {
            mkdir($prefix, 0777, true);
        }
        $filename = $prefix . '/' . $name;
        $contents = "[MidgardDir]\n\n";
        $contents .= $this->convert_to_storage('ShareDir', $this->sharedir);
        $contents .= $this->convert_to_storage('VarDir', $this->vardir);
        $contents .= $this->convert_to_storage('BlobDir', $this->blobdir);
        $contents .= $this->convert_to_storage('CacheDir', $this->cachedir);
        $contents .= "[Midgarddatabase]\n\n";
        $contents .= $this->convert_to_storage('Type', $this->dbtype);
        $contents .= $this->convert_to_storage('Host', $this->host);
        $contents .= $this->convert_to_storage('Port', $this->port);
        $contents .= $this->convert_to_storage('Name', $this->database);
        $contents .= $this->convert_to_storage('Username', $this->dbuser);
        $contents .= $this->convert_to_storage('Password', $this->dbpass);
        $contents .= $this->convert_to_storage('DatabaseDir', $this->dbdir);
        $contents .= "DefaultLanguage = pl\n\n";
        $contents .= $this->convert_to_storage('Logfile', $this->logfilename);
        $contents .= $this->convert_to_storage('Loglevel', $this->loglevel);
        $contents .= $this->convert_to_storage('TableCreate', $this->tablecreate);
        $contents .= $this->convert_to_storage('TableUpdate', $this->tableupdate);
        $contents .= $this->convert_to_storage('TestUnit', $this->testunit);
        $contents .= $this->convert_to_storage('MidgardUsername', $this->midgardusername);
        $contents .= $this->convert_to_storage('MidgardPassword', $this->midgardpassword);
        $contents .= $this->convert_to_storage('AuthType', $this->authtype);
        $contents .= $this->convert_to_storage('PamFile', $this->pamfile);
        $contents .= $this->convert_to_storage('GdaThreads', $this->gdathreads);

        $stat = file_put_contents($filename, $contents);
        if ($stat === false)
        {
            return false;
        }
        return true;
    }

    private function convert_to_storage($key, $value)
    {
        if (is_bool($value))
        {
            $value = ($value) ? 'true' : 'false';
        }
        else if ($value === '')
        {
            $value = '""';
        }
        return $key . ' = ' . $value . "\n\n";
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