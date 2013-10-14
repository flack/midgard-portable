<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\storage\driver;
use midgard\portable\storage\connection;
use midgard_dbobject;

class testcase extends \PHPUnit_Framework_TestCase
{
    public static $ns;

    public static $em;

    public static function setupBeforeClass()
    {
        self::$ns = uniqid(get_called_class());

        $driver = new driver(array(TESTDIR . '__files/'), sys_get_temp_dir(), self::$ns);
        include TESTDIR . DIRECTORY_SEPARATOR . 'bootstrap.php';
        self::$em = connection::get_em();
    }

    protected function count_results($classname, $include_deleted = false)
    {
        self::$em->clear();
        if ($include_deleted)
        {
            self::$em->getFilters()->disable('softdelete');
        }
        $count = self::$em->createQuery('SELECT COUNT(a) FROM ' . $classname . ' a')->getSingleScalarResult();
        if ($include_deleted)
        {
            self::$em->getFilters()->enable('softdelete');
        }

        return (int) $count;
    }
}