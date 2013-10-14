<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

class application
{
    private $cache_dir;

    private static $typecache;

    public function __construct($cache_dir)
    {
        $this->cache_dir = $cache_dir;
        if (!is_dir($cache_dir))
        {
            mkdir($cache_dir);
        }
        if (!is_dir($cache_dir) . '/typecache')
        {
            mkdir($cache_dir . '/typecache');
        }
    }

    public function run($schemadir)
    {
        self::$typecache = new typecache($this->cache_dir);
        self::$typecache->load($schemadir);
    }

    public static function get_typecache()
    {
        return self::$typecache;
    }
}

//Call like this:
$bootstrapper = new application(dirname(dirname(__DIR__)) . '__filecache');
$bootstrapper->run($schemadir);