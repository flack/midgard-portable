<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_storage;
use midgard\portable\storage\connection;

class midgard_storageTest extends testcase
{
    private function prepare_dtn_connection()
    {
        self::prepare_connection('duplicate_tablenames/', sys_get_temp_dir(), uniqid(__CLASS__ . __FUNCTION__));
    }

    private function clear_user_table()
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = [
            $factory->getMetadataFor(connection::get_fqcn('midgard_user')),
        ];
        $tool->dropSchema($classes);
    }

    public function test_create_base_storage()
    {
        self::prepare_connection();
        $this->clear_user_table();

        $stat = midgard_storage::create_base_storage();
        $this->assertTrue($stat);
        $cm = self::$em->getMetadataFactory()->getMetadataFor(connection::get_fqcn('midgard_user'));
        $this->assertInstanceOf('midgard\portable\mapping\classmetadata', $cm);

        $fqcn = $cm->fullyQualifiedClassName('midgard_user');
        $tokens = [
            'authtype' => 'Legacy',
            'login' => 'admin'
        ];
        $admin = new $fqcn($tokens);
        $this->assertEquals(2, $admin->usertype);
        $this->assertTrue(midgard_storage::create_base_storage());
        $this->assertTrue(password_verify('password', $admin->password));
    }

    private function assertCreateClassStorageSuccess($classname)
    {
        $this->assertTrue(midgard_storage::create_class_storage($classname), 'Failed to create the class storage for ' . $classname);
    }

    public function test_create_class_storage()
    {
        self::prepare_connection();

        $this->assertTrue(midgard_storage::create_class_storage('midgard_topic'));
        $this->assertTrue(midgard_storage::create_class_storage('midgard_topic'));
        $this->assertTrue(self::$em->getConnection()->getSchemaManager()->tablesExist(['topic']));
        $this->assertFalse(midgard_storage::create_class_storage('nonexistent'));

        // check duplicate tablenames
        self::prepare_dtn_connection();

        $this->assertCreateClassStorageSuccess('midgard_group');
        $this->assertCreateClassStorageSuccess('org_openpsa_organization');
        $this->assertCreateClassStorageSuccess('org_openpsa_contacts_list');
    }

    private function assertUpdateClassStorageSuccess($classname)
    {
        $this->assertTrue(midgard_storage::update_class_storage($classname), 'Failed to update the class storage for ' . $classname);
    }

    private function assertUpdateClassStorageFail($classname)
    {
        $this->assertFalse(midgard_storage::update_class_storage($classname), 'Without previous creation, the update of the class storage for ' . $classname . ' should fail');
    }

    public function test_update_class_storage()
    {
        self::prepare_connection();

        midgard_storage::create_base_storage();
        $this->assertTrue(midgard_storage::create_class_storage('midgard_topic'));

        $cm = self::$em->getMetadataFactory()->getMetadataFor(connection::get_fqcn('midgard_topic'));
        $cm->mapField(['type' => 'string', 'fieldName' => 'testproperty', 'default' => '']);

        $this->assertTrue(midgard_storage::update_class_storage('midgard_topic'));
        $table = self::$em->getConnection()->getSchemaManager()->createSchema()->getTable('topic');
        $this->assertTrue($table->hasColumn('testproperty'));

        //when removing the field from the schema, the column should stay in the DB
        unset($cm->fieldMappings['testproperty']);
        $this->assertTrue(midgard_storage::update_class_storage('midgard_topic'));
        $table = self::$em->getConnection()->getSchemaManager()->createSchema()->getTable('topic');
        $this->assertTrue($table->hasColumn('testproperty'));

        $this->assertTrue(self::$em->getConnection()->getSchemaManager()->tablesExist(['midgard_user']));

        // check duplicate tablenames
        self::prepare_dtn_connection();
        midgard_storage::create_class_storage('midgard_group');

        $this->assertUpdateClassStorageSuccess('midgard_group');
        $this->assertUpdateClassStorageSuccess('org_openpsa_organization');
        $this->assertUpdateClassStorageSuccess('org_openpsa_contacts_list');
    }

    private function assertClassStorageExists($classname)
    {
        $this->assertTrue(midgard_storage::class_storage_exists($classname), 'We should have a class storage for ' . $classname);
    }

    public function test_class_storage_exists()
    {
        self::prepare_connection();
        midgard_storage::create_class_storage('midgard_topic');

        $this->assertTrue(midgard_storage::class_storage_exists('midgard_topic'));
        $this->assertFalse(midgard_storage::class_storage_exists('some_random_string'));

        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = $factory->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        // check duplicate tablenames
        self::prepare_dtn_connection();
        midgard_storage::create_class_storage('midgard_group');

        // we should find metadata for all classes that use "grp" table
        $this->assertClassStorageExists('midgard_group');
        $this->assertClassStorageExists('org_openpsa_organization');
        $this->assertClassStorageExists('org_openpsa_contacts_list');
    }
}
