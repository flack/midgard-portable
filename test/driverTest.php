<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\driver;
use midgard\portable\classgenerator;
use midgard\portable\mgdschema\property;
use midgard\portable\mgdschema\type;
use PHPUnit\Framework\Attributes\DataProvider;
use midgard\portable\mapping\classmetadata;

class driverTest extends testcase
{
    public function test_getAllClassNames()
    {
        $ns = uniqid(__CLASS__ . '\\' . __FUNCTION__);
        $d = sys_get_temp_dir();
        $driver = new driver([TESTDIR . '__files/'], $d, $ns);
        $classnames = $driver->getAllClassNames();
        $this->assertIsArray($classnames);
        $this->assertCount(12, $classnames);
    }

    public function test_loadMetadataForClass()
    {
        $ns = uniqid(__CLASS__ . '\\' . __FUNCTION__);
        $d = sys_get_temp_dir();
        $driver = new driver([TESTDIR . '__files/'], $d, $ns);
        $metadata = new classmetadata($ns . '\\midgard_topic');
        $driver->loadMetadataForClass($ns . '\\midgard_topic', $metadata);

        $this->assertArrayHasKey('metadata_deleted', $metadata->fieldMappings);
        $this->assertArrayHasKey('score', $metadata->fieldMappings);

        $mapping = $metadata->fieldMappings['metadata_approved'];
        $this->assertEquals("datetime", $mapping["type"]);
        $this->assertEquals("0001-01-01 00:00:00", $mapping["default"]);
    }

    public function test_load_nonportable_dbtypes()
    {
        $ns = uniqid(__CLASS__ . '\\' . __FUNCTION__);
        $d = sys_get_temp_dir();
        $driver = new driver([TESTDIR . '__files/'], $d, $ns);
        $metadata = new classmetadata($ns . '\\nonportable_dbtype');
        $driver->loadMetadataForClass($ns . '\\nonportable_dbtype', $metadata);

        $this->assertArrayHasKey('info', $metadata->fieldMappings);
        $this->assertArrayHasKey('password', $metadata->fieldMappings);

        $mapping = $metadata->fieldMappings['password'];
        $this->assertEquals("BINARY", $mapping["options"]["comment"]);

        $mapping = $metadata->fieldMappings['info'];
        $this->assertEquals("set('auth')", $mapping["options"]["comment"]);
    }

    #[DataProvider('provider_set_notation')]
    public function test_set_notation(string $dbtype, string $expected)
    {
        $type = new type(new \SimpleXMLElement('<type name="test" table="test"/>'));
        $property = new property($type, 'test', 'string');
        $property->set('dbtype', $dbtype);

        $driver = new driver([], sys_get_temp_dir(), uniqid(__CLASS__ . '\\' . __FUNCTION__));
        $mapping = (new \ReflectionMethod($driver, 'parse_dbtype'))->invoke($driver, $property);
        $this->assertSame($expected, $mapping['options']['comment']);
    }

    public static function provider_set_notation()
    {
        return [
            ["set('a')", "set('a')"],
            ["set('a','b')", "set('a','b')"],
            ["set('a', 'b')", "set('a','b')"],
            ["set ( 'a' , 'b c' ) ", "set('a','b c')"],
        ];
    }

    public function test_duplicate_tablenames()
    {
        $ns = uniqid(__CLASS__ . '\\' . __FUNCTION__);
        $d = sys_get_temp_dir();
        $driver = new driver([TESTDIR . '__files/duplicate_tablenames/'], $d, $ns);
        $classgenerator = new classgenerator($driver->get_manager(), $d . '/mgdschema_classes.php');
        $classgenerator->write($ns);
        include $d . '/mgdschema_classes.php';

        $classname = $ns . '\\midgard_member';
        $classname = get_class(new $classname);
        $metadata_member = new classmetadata($classname);
        $driver->loadMetadataForClass($classname, $metadata_member);
        $this->assertEquals('gid', $metadata_member->midgard['parentfield']);

        $classname = $ns . '\\midgard_group';
        $classname = get_class(new $classname);
        $metadata_group = new classmetadata($classname);
        $driver->loadMetadataForClass($classname, $metadata_group);

        $this->assertCount(2, $metadata_group->midgard['childtypes']);
    }
}
