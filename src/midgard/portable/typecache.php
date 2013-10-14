<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\schema;

class typecache
{
    private $data;

    private $filename;

    /**
     *
     * @param string $cache_dir
     */
    public function __construct($cache_dir)
    {
        $this->filename = $cache_dir . '/typecache.php';
    }

    public function load()
    {
        if (!file_exists($this->filename))
        {
            return false;
        }
        $this->types = unserialize(file_get_contents($this->filename));
    }

    private function save()
    {
        file_put_contents($this->filename, serialize($this->types));
    }

    /**
     *
     * @param string $classname
     * @return midgard\portable\schema\type
     */
    public function get_type($classname)
    {
        return $this->types[$classname];
    }

    /**
     *
     * @param string $name
     * @param midgard\portable\schema\type $type
     * @throws \Exception
     */
    public function set_type($name, type $type)
    {
        if (array_key_exists($name, $this->types))
        {
            throw new \Exception('Type ' . $name . ' is already registered');
        }
        $this->types[$name] = $type;
    }
}