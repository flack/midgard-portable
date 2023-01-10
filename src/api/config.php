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
    public $midgardusername = 'admin';
    public $midgardpassword = 'password';
    public $authtype = '';
    public $pamfile = '';
    public $blobdir = '/var/lib/midgard2/blobs';
    public $sharedir = '/usr/share/midgard2';
    public $vardir = '/var/lib/midgard2';
    public $cachedir = '/var/cache/midgard2';
    public $tablecreate = false;

    public function read_file_at_path(string $path) : bool
    {
        if (!is_readable($path)) {
            return false;
        }
        $parsed = parse_ini_file($path);

        $this->apply_config($parsed);

        return true;
    }

    // TODO: find out if this could be moved to read_data()
    private function apply_config(array $config)
    {
        $mapping = [
            'type' => 'dbtype',
            'name' => 'database',
            'username' => 'dbuser',
            'password' => 'dbpass',
            'databasedir' => 'dbdir',
            'logfilename' => 'logfile',
        ];

        foreach ($config as $key => $value) {
            $key = strtolower($key);
            if (isset($mapping[$key])) {
                $key = $mapping[$key];
            }
            if (property_exists($this, $key)) {
                if (is_bool($this->$key)) {
                    $value = (boolean) $value;
                }
                $this->$key = $value;
            }
        }
    }

    private function get_prefix(bool $user) : string
    {
        if ($user) {
            return getenv('HOME') . '/.midgard2/conf.d';
        }
        return '/etc/midgard2/conf.d';
    }

    public function read_file(string $name, bool $user = true) : bool // <== TODO: check
    {
        return $this->read_file_at_path($this->get_prefix($user) . '/' . $name);
    }

    public function save_file(string $name, bool $user = true) : bool // <== TODO: check
    {
        $prefix = $this->get_prefix($user);
        if (!file_exists($prefix)) {
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
        $contents .= $this->convert_to_storage('MidgardUsername', $this->midgardusername);
        $contents .= $this->convert_to_storage('MidgardPassword', $this->midgardpassword);
        $contents .= $this->convert_to_storage('AuthType', $this->authtype);
        $contents .= $this->convert_to_storage('PamFile', $this->pamfile);

        return file_put_contents($filename, $contents) !== false;
    }

    private function convert_to_storage(string $key, $value) : string
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif ($value === '') {
            $value = '""';
        }
        return $key . ' = ' . $value . "\n\n";
    }

    public function create_blobdir() : bool
    {
        $subdirs = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F'];
        foreach ($subdirs as $dir) {
            foreach ($subdirs as $subdir) {
                if (   !is_dir($this->blobdir . '/' . $dir . '/' . $subdir)
                    && !mkdir($this->blobdir . '/' . $dir . '/' . $subdir, 0777, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
