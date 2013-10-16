<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\driver;
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

    /**
     * purge all records for the given classname
     *
     * @param string $classname
     * @return number the number of deleted rows
     */
    protected function purge_all($classname)
    {
        // delete all, no matter what
        self::$em->getFilters()->disable('softdelete');
        $q = self::$em->createQuery('DELETE FROM ' . $classname);
        $count = $q->execute();
        self::$em->getFilters()->enable('softdelete');
        return $count;
    }

    /**
     *
     * @param string $classname
     * @param boolean $include_deleted
     * @return number the total number of records for this classname
     */
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