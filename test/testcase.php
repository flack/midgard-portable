<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\driver;
use midgard\portable\api\dbobject;
use midgard\portable\api\error\exception;
use midgard\portable\storage\connection;
use midgard_connection;
use PHPUnit\Framework\TestCase as basecase;

class testcase extends basecase
{
    public static $em;

    public static function setupBeforeClass() : void
    {
        self::prepare_connection();
    }

    public static function prepare_connection(string $directory = '', $tmpdir = null, $ns = null) : driver
    {
        if ($tmpdir === null) {
            $tmpdir = sys_get_temp_dir();
        }
        if ($ns === null) {
            $ns = uniqid(get_called_class());
        }
        $directories = [
            TESTDIR . '__files/' . $directory
        ];

        $driver = new driver($directories, $tmpdir, $ns);

        $db = getenv('DB');
        if (!empty($db)) {
            $db_config = require __DIR__ . DIRECTORY_SEPARATOR . $db . '.inc';
        } else {
            $db_config = [
                'memory' => true,
                'driver' => 'pdo_sqlite'
            ];
        }
        connection::initialize($driver, $db_config, true);

        self::$em = connection::get_em();
        return $driver;
    }

    /**
     * @return \Doctrine\ORM\Mapping\ClassMetadata[]
     */
    protected static function get_metadata(array $classnames) : array
    {
        $factory = self::$em->getMetadataFactory();
        $classes = [];

        foreach ($classnames as $name) {
            $classes[] = $factory->getMetadataFor(connection::get_fqcn($name));
        }
        return $classes;
    }

    protected static function create_user()
    {
        $person_class = connection::get_fqcn('midgard_person');
        $user_class = connection::get_fqcn('midgard_user');
        $person = new $person_class;
        $person->create();
        $user = new $user_class;
        $user->authtype = 'Legacy';
        $user->set_person($person);
        $user->create();
        $user->login();
        return $person;
    }

    protected function make_object(string $classname, $constructor_args = null) : dbobject
    {
        if ($classname == 'midgard_user' && $constructor_args === null) {
            $constructor_args = [];
        }
        $classname = connection::get_fqcn($classname);
        return new $classname($constructor_args);
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
        $q = self::$em->createQuery('DELETE ' . connection::get_fqcn($classname) . ' c');
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
        if ($include_deleted) {
            self::$em->getFilters()->disable('softdelete');
        }
        $count = self::$em->createQuery('SELECT COUNT(a) FROM ' . connection::get_fqcn($classname) . ' a')->getSingleScalarResult();
        if ($include_deleted) {
            self::$em->getFilters()->enable('softdelete');
        }

        return (int) $count;
    }

    protected function verify_unpersisted_changes($classname, $guid, $cmp_field, $cmp_value)
    {
        // make sure unpersisted changes has not been persisted
        $qb = new \midgard_query_builder(connection::get_fqcn($classname));
        $qb->include_deleted();
        $qb->add_constraint('guid', '=', $guid);
        $results = $qb->execute();
        $this->assertCount(1, $results);
        $loaded = array_shift($results);
        $this->assertEquals($cmp_value, $loaded->{$cmp_field}, "This object change for field \"" . $cmp_field . "\" should have not been persisted!");
    }

    protected function assert_api($function, dbobject $object, $expected_error = MGD_ERR_OK)
    {
        $this->assertEquals(($expected_error === MGD_ERR_OK), $object->$function(), $function . '() returned: ' . midgard_connection::get_instance()->get_error_string());
        $this->assert_error($expected_error);
    }

    protected function assert_error($error_code)
    {
        $this->assertEquals(exception::get_error_string($error_code), exception::get_error_string(midgard_connection::get_instance()->get_error()), 'Unexpected error code');
    }
}
