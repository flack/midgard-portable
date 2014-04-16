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
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = array(
            $factory->getMetadataFor('midgard:midgard_user'),
        );
        $tool->dropSchema($classes);

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

    public function test_create_class_storage()
    {
        $this->assertTrue(midgard_storage::create_class_storage('midgard_topic'));
        $this->assertTrue(midgard_storage::create_class_storage('midgard_topic'));
        $this->assertTrue(self::$em->getConnection()->getSchemaManager()->tablesExist(array('topic')));
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
    }

    public function test_class_storage_exists()
    {
        $this->assertTrue(midgard_storage::class_storage_exists('midgard_topic'));
        $this->assertFalse(midgard_storage::class_storage_exists('some_random_string'));
    }
}