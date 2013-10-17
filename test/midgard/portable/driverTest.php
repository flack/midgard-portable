<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\driver;
use Doctrine\ORM\Mapping\ClassMetadata;

class driverTest extends \PHPUnit_Framework_TestCase
{
    public function test_getAllClassNames()
    {
        $ns = uniqid(__CLASS__ . '\\' . __FUNCTION__);
        $d = sys_get_temp_dir();
        $driver = new driver(array(TESTDIR . '__files/'), $d, $ns);
        $classnames = $driver->getAllClassNames();
        $this->assertInternalType('array', $classnames);
        $this->assertEquals(8, count($classnames));

        foreach ($classnames as $classname)
        {
            $this->assertTrue(class_exists($classname), $classname . ' not found');
        }
    }

    public function test_loadMetadataForClass()
    {
        $ns = uniqid(__CLASS__ . '\\' . __FUNCTION__);
        $d = sys_get_temp_dir();
        $driver = new driver(array(TESTDIR . '__files/'), $d, $ns);
        $metadata = new ClassMetadata($ns . '\\midgard_topic');
        $driver->loadMetadataForClass($ns . '\\midgard_topic', $metadata);

        $this->assertArrayHasKey('metadata_deleted', $metadata->fieldMappings);
        $this->assertArrayHasKey('score', $metadata->fieldMappings);
    }

    public function test_duplicate_tablename()
    {
        $ns = uniqid(__CLASS__ . '\\' . __FUNCTION__);
        $d = sys_get_temp_dir();
        $driver = new driver(array(TESTDIR . '__files/duplicate_tablenames/'), $d, $ns);

        //TODO: Get this to work somehow (or at least QB and such)
        //$metadata = new ClassMetadata($ns . '\\midgard_group');
        //$driver->loadMetadataForClass($ns . '\\midgard_group', $metadata);
    }
}