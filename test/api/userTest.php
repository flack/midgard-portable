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

class userTest extends testcase
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        $tool = new \Doctrine\ORM\Tools\SchemaTool(self::$em);
        $factory = self::$em->getMetadataFactory();
        $classes = array(
            $factory->getMetadataFor('midgard:midgard_user'),
            $factory->getMetadataFor('midgard:midgard_person'),
            $factory->getMetadataFor('midgard:midgard_repligard'),
        );
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function test_create()
    {
        $classname = self::$ns . '\\midgard_user';
        $initial = $this->count_results('midgard:midgard_user');

        $user = new $classname;
        $stat = $user->create();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_INVALID_PROPERTY_VALUE, midgard_connection::get_instance()->get_error());

        $user->authtype = 'Legacy';
        $stat = $user->create();
        $this->assertTrue($stat);
        $this->assertEquals(MGD_ERR_OK, midgard_connection::get_instance()->get_error());
        $this->assertEquals($initial + 1, $this->count_results('midgard:midgard_user'));

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
        $classname = self::$ns . '\\midgard_user';
        $user = new $classname;
        $user->authtype = 'Legacy';
        $stat = $user->create();

        $user->login = uniqid();
        $user->password = 'x';
        $stat = $user->update();
        $this->assertTrue($stat, midgard_connection::get_instance()->get_error_string());

        self::$em->clear();
        $tokens = array('authtype' => $user->authtype, 'login' => $user->login, 'password' => $user->password);
        $loaded = new $classname($tokens);
        $this->assertEquals($loaded->login, $user->login);
        
        $user2 = new $classname;
        $user2->login = uniqid(__FUNCTION__);
        $user2->password = 'x';
        $user2->authtype = 'Legacy';
        $stat = $user2->create();
        $this->assertTrue($stat);
        
        //set same login - should not work
        $user2->login = $user->login;
        $stat = $user2->update();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_DUPLICATE, midgard_connection::get_instance()->get_error());
        
        //incorrect guid - should not work
        $user->guid = 0;
        $stat = $user->update();
        $this->assertFalse($stat);
        $this->assertEquals(MGD_ERR_INVALID_PROPERTY_VALUE, midgard_connection::get_instance()->get_error());
    }

    public function test_delete()
    {
        $classname = self::$ns . '\\midgard_user';
        $initial = $this->count_results('midgard:midgard_user');

        $user = new $classname;
        $user->authtype = 'Legacy';
        $user->create();

        $stat = $user->delete();
        $this->assertTrue($stat);
        $this->assertEquals('', $user->guid);
        $this->assertEquals($initial, $this->count_results('midgard:midgard_user'));
    }

    public function test_get_id()
    {
        $classname = self::$ns . '\\midgard_user';
        $user = new $classname;

        //This checks the value with reflection internally and expects null
        $this->assertSame(UnitOfWork::STATE_NEW, self::$em->getUnitOfWork()->getEntityState($user));
        $this->assertSame(0, $user->id);
    }

    public function test_login()
    {
        $classname = self::$ns . '\\midgard_user';

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
        $classname = self::$ns . '\\midgard_user';

        $user = new $classname;
        $this->assertFalse($user->is_admin());
        $user->usertype = 2;
        $this->assertTrue($user->is_admin());
    }

    public function test_login_with_credentials()
    {
        $classname = self::$ns . '\\midgard_user';

        $user = new $classname;
        $user->login = uniqid();
        $user->password = 'x';
        $user->authtype = 'Legacy';
        $user->create();
        self::$em->clear();

        $tokens = array('authtype' => $user->authtype, 'login' => $user->login, 'password' => $user->password);

        $user2 = new $classname($tokens);
        $stat = $user2->login();
        $this->assertTrue($stat);
        $this->assertEquals($user->id, connection::get_user()->id);
    }

    /**
     * @expectedException midgard_error_exception
     */
    public function test_login_with_wrong_credentials()
    {
        $classname = self::$ns . '\\midgard_user';

        $user = new $classname;
        $user->login = uniqid();
        $user->password = 'x';
        $user->authtype = 'Legacy';
        $user->create();
        self::$em->clear();

        $tokens = array('authtype' => $user->authtype, 'login' => $user->login, 'password' => $user->password . 'x');

        $user2 = new $classname($tokens);
    }

    /**
     * @expectedException midgard_error_exception
     */
    public function test_login_with_invalid_credentials()
    {
        $classname = self::$ns . '\\midgard_user';

        $user = new $classname;
        $user->login = uniqid();
        $user->password = 'x';
        $user->authtype = 'Legacy';
        $user->create();
        self::$em->clear();

        $tokens = array('authtype' => $user->authtype, 'password' => $user->password);

        $user2 = new $classname($tokens);
    }

    public function test_logout()
    {
        $classname = self::$ns . '\\midgard_user';

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
        $person_class = self::$ns . '\\midgard_person';
        $classname = self::$ns . '\\midgard_user';

        $person = new $person_class;
        $person->create();

        $user = new $classname;
        $user->authtype = 'Legacy';
        $user->set_person($person);
        $user->create();
        self::$em->clear();

        $loaded = self::$em->find('midgard:midgard_user', $user->id);

        $this->assertEquals($person->guid, $loaded->get_person()->guid);
    }
}