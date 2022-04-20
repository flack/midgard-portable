<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\storage\connection;
use midgard_connection;
use Doctrine\ORM\UnitOfWork;
use midgard\portable\api\error\exception;

class userTest extends testcase
{
    public static function setupBeforeClass() : void
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = [
            $factory->getMetadataFor(connection::get_fqcn('midgard_user')),
            $factory->getMetadataFor(connection::get_fqcn('midgard_person')),
            $factory->getMetadataFor(connection::get_fqcn('midgard_repligard')),
        ];
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_create()
    {
        $classname = connection::get_fqcn('midgard_user');
        $initial = $this->count_results($classname);

        $user = new $classname;
        $stat = $user->create();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_INVALID_PROPERTY_VALUE, midgard_connection::get_instance()->get_error());

        $user->authtype = 'Legacy';
        $stat = $user->create();
        $this->assertTrue($stat);
        $this->assertEquals(MGD_ERR_OK, midgard_connection::get_instance()->get_error());
        $this->assertEquals($initial + 1, $this->count_results($classname));
        $this->assertTrue(mgd_is_guid($user->guid));

        $stat = $user->create();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_INVALID_PROPERTY_VALUE, midgard_connection::get_instance()->get_error());

        $user2 = new $classname;
        $user2->login = uniqid(__FUNCTION__);
        $user2->password = 'x';
        $user2->authtype = 'Legacy';
        $stat = $user2->create();
        $this->assertTrue($stat);

        $user3 = new $classname;
        $user3->login = $user2->login;
        $user3->password = 'x';
        $user3->authtype = 'Legacy';
        $stat = $user3->create();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_DUPLICATE, midgard_connection::get_instance()->get_error());
    }

    public function test_update()
    {
        $classname = connection::get_fqcn('midgard_user');
        $user = new $classname;
        $user->authtype = 'Legacy';
        $stat = $user->create();

        $user->login = uniqid();
        $user->password = 'x';
        $stat = $user->update();
        $this->assertTrue($stat, midgard_connection::get_instance()->get_error_string());

        self::$em->clear();
        $tokens = ['authtype' => $user->authtype, 'login' => $user->login, 'password' => $user->password];
        $loaded = new $classname($tokens);
        $this->assertEquals($loaded->login, $user->login);

        $user2 = new $classname;
        $user2->login = uniqid(__FUNCTION__);
        $user2->password = 'x';
        $user2->authtype = 'Legacy';
        $this->assert_api('create', $user2);

        //set same login - should not work
        $user2->login = $user->login;
        $this->assert_api('update', $user2, MGD_ERR_DUPLICATE);

        //incorrect guid - should not work
        $ref = new \ReflectionClass($user);
        $guid = $ref->getProperty('guid');
        $guid->setAccessible(true);
        $guid->setValue($user, 'x');

        $this->assert_api('update', $user, MGD_ERR_INVALID_PROPERTY_VALUE);
        $user2->login = uniqid('xx');
        $this->assert_api('update', $user2);
    }

    public function test_delete()
    {
        $classname = connection::get_fqcn('midgard_user');
        $initial = $this->count_results($classname);

        $user = new $classname;
        $user->authtype = 'Legacy';
        $user2 = new $classname;
        $user2->authtype = 'Legacy';
        $this->assert_api('create', $user2);

        $this->assert_api('delete', $user, MGD_ERR_INVALID_PROPERTY_VALUE);
        $this->assert_api('create', $user);

        $this->assert_api('delete', $user);
        $this->assert_api('delete', $user, MGD_ERR_INVALID_PROPERTY_VALUE);
        $this->assert_api('delete', $user2);
        $this->assertEquals('', $user->guid);
        $this->assertEquals($initial, $this->count_results($classname));
    }

    public function test_get_id()
    {
        $classname = connection::get_fqcn('midgard_user');
        $user = new $classname;

        //This checks the value with reflection internally and expects null
        $this->assertSame(UnitOfWork::STATE_NEW, self::$em->getUnitOfWork()->getEntityState($user));
        $this->assertSame(0, $user->id);
    }

    public function test_set_guid()
    {
        $classname = connection::get_fqcn('midgard_user');
        $user = new $classname;
        $user->authtype = 'Legacy';
        $user->login = uniqid();
        $user->password = 'x';
        $this->assert_api('create', $user);

        $guid = $user->guid;
        $user->guid = 'x';
        $this->assertSame($guid, $user->guid);
    }

    public function test_login()
    {
        $classname = connection::get_fqcn('midgard_user');

        $user = new $classname;
        $stat = $user->login();
        $this->assertFalse($stat);

        $user->authtype = 'Legacy';
        $user->create();
        $stat = $user->login();
        $this->assertTrue($stat);
        $this->assertEquals($user, connection::get_user());
    }

    public function test_is_admin()
    {
        $classname = connection::get_fqcn('midgard_user');

        $user = new $classname;
        $this->assertFalse($user->is_admin());
        $user->usertype = 2;
        $this->assertTrue($user->is_admin());
    }

    public function test_login_with_credentials()
    {
        $classname = connection::get_fqcn('midgard_user');

        $user = new $classname;
        $user->login = uniqid();
        $user->password = 'x';
        $user->authtype = 'Legacy';
        $user->create();
        self::$em->clear();

        $tokens = ['authtype' => $user->authtype, 'login' => $user->login, 'password' => $user->password];

        $user2 = new $classname($tokens);
        $stat = $user2->login();
        $this->assertTrue($stat);
        $this->assertEquals($user->id, connection::get_user()->id);
    }

    public function test_login_with_wrong_credentials()
    {
        $classname = connection::get_fqcn('midgard_user');

        $user = new $classname;
        $user->login = uniqid();
        $user->password = 'x';
        $user->authtype = 'Legacy';
        $user->create();
        self::$em->clear();

        $tokens = ['authtype' => $user->authtype, 'login' => $user->login, 'password' => $user->password . 'x'];

        $this->expectException(exception::class);
        new $classname($tokens);
    }

    public function test_login_with_invalid_credentials()
    {
        $classname = connection::get_fqcn('midgard_user');

        $user = new $classname;
        $user->login = uniqid();
        $user->password = 'x';
        $user->authtype = 'Legacy';
        $user->create();
        self::$em->clear();

        $tokens = ['authtype' => $user->authtype, 'password' => $user->password];

        $this->expectException(exception::class);
        new $classname($tokens);
    }

    public function test_logout()
    {
        $classname = connection::get_fqcn('midgard_user');

        $user = new $classname;
        $user->authtype = 'Legacy';
        $stat = $user->logout();
        $this->assertFalse($stat);

        $user->create();
        $user->login();
        $stat = $user->logout();
        $this->assertTrue($stat);
        $this->assertEquals(null, connection::get_user());
    }

    public function test_get_person()
    {
        $person_class = connection::get_fqcn('midgard_person');
        $classname = connection::get_fqcn('midgard_user');

        $person = new $person_class;
        $person->create();

        $user = new $classname;
        $user->authtype = 'Legacy';
        $user->set_person($person);
        $user->create();
        self::$em->clear();

        $loaded = self::$em->find($classname, $user->id);

        $this->assertEquals($person->guid, $loaded->get_person()->guid);
    }
}
