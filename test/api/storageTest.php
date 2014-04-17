<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard_storage;
use midgard_query_builder;

class midgard_storageTest extends testcase
{
    public function test_create_base_storage()
    {
        // check for duplicate tablenames
        self::$ns = uniqid(__CLASS__);
        self::prepare_connection(array(TESTDIR . '__files/duplicate_tablenames/'), sys_get_temp_dir(), self::$ns);

        $stat = midgard_storage::create_base_storage();
        $this->assertTrue($stat);
        $cm = self::$em->getMetadataFactory()->getMetadataFor('midgard:midgard_user');
        $this->assertInstanceOf('midgard\portable\mapping\classmetadata', $cm);

        $fqcn = $cm->fullyQualifiedClassName('midgard_user');
        $tokens = array
        (
            'authtype' => 'Plaintext',
            'login' => 'admin',
            'password' => 'password',
        );
        $admin = new $fqcn($tokens);
        $this->assertEquals(2, $admin->usertype);
        $this->assertTrue(midgard_storage::create_base_storage());
    }

    private function assertCreateClassStorageSuccess($classname)
    {
        $this->assertTrue(midgard_storage::create_class_storage($classname), 'Failed to create the class storage for ' . $classname);
    }

    public function test_create_class_storage()
    {
        $this->assertTrue(midgard_storage::create_class_storage('midgard_topic'));
        $this->assertTrue(midgard_storage::create_class_storage('midgard_topic'));
        $this->assertTrue(self::$em->getConnection()->getSchemaManager()->tablesExist(array('topic')));

        $this->assertCreateClassStorageSuccess('midgard_group');
        $this->assertCreateClassStorageSuccess('org_openpsa_organization');
        $this->assertCreateClassStorageSuccess('org_openpsa_contacts_list');
    }

    private function assertUpdateClassStorageSuccess($classname)
    {
        $this->assertTrue(midgard_storage::update_class_storage($classname), 'Failed to update the class storage for ' . $classname);
    }

    public function test_update_class_storage()
    {
        midgard_storage::create_base_storage();

        $cm = self::$em->getMetadataFactory()->getMetadataFor('midgard:midgard_topic');
        $cm->mapField(array('type' => 'string', 'fieldName' => 'testproperty'));

        $this->assertTrue(midgard_storage::update_class_storage('midgard_topic'));
        $this->assertTrue(midgard_storage::update_class_storage('midgard_topic'));

        $table = self::$em->getConnection()->getSchemaManager()->createSchema()->getTable('topic');
        $this->assertTrue($table->hasColumn('testproperty'));

        $this->assertTrue(self::$em->getConnection()->getSchemaManager()->tablesExist(array('midgard_user')));

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
        $this->assertTrue(midgard_storage::class_storage_exists('midgard_topic'));
        $this->assertFalse(midgard_storage::class_storage_exists('some_random_string'));

        // check for duplicate tablenames
        //$ns = uniqid(__CLASS__ . '__' . __FUNCTION__);
        //self::prepare_connection(array(TESTDIR . '__files/duplicate_tablenames/'), sys_get_temp_dir(), $ns);

        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = $factory->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        // we should find metadata for all classes that use "grp" table
        $this->assertClassStorageExists('midgard_group');
        $this->assertClassStorageExists('org_openpsa_organization');
        $this->assertClassStorageExists('org_openpsa_contacts_list');
    }
}