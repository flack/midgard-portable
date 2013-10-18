<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\api;

use midgard\portable\storage\connection;
use midgard_user;
use midgard_person;
use midgard_error_exception;

class user extends midgard_user
{
    private $person_object;

    protected $id = 0;

    protected $properties = array();

    protected $person;

    protected $guid = '';

    protected $login = '';

    protected $password = '';

    protected $active = false;

    protected $authtype = '';

    protected $authtypeid = 0;

    protected $usertype = 0;

    public static function &get($properties)
    {

    }

    public static function &query($properties)
    {

    }

    public function __construct(array $properties = array())
    {
        if (!empty($properties))
        {
            $this->load_by_properties($properties);
        }
    }

    private function load_by_properties(array $properties)
    {
        if (   !array_key_exists('authtype', $properties)
            || !array_key_exists('login', $properties))
        {
            throw new midgard_error_exception('Invalid property value.');
        }
        $entity = connection::get_em()->getRepository('midgard:midgard_user')->findOneBy($properties);

        if ($entity === null)
        {
            throw new midgard_error_exception('Object does not exist.');
        }
        $this->populate_from_entity($entity);
    }

	public function login()
    {
        if ($this->id == 0)
        {
            return false;
        }
        connection::set_user($this);
    	return true;
    }

    public function logout()
    {
        if ($this->id == 0)
        {
            return false;
        }
        connection::set_user(null);
    	return true;
    }

    public function is_user()
    {
        return false;
    }

    public function is_admin()
    {
        return false;
    }

    public function set_person(midgard_person $person)
    {
        $this->person_object = $person;
        $this->person = $person->guid;
    }

    public function &get_person()
    {
        if (   $this->person_object === null
            && $this->person !== null)
        {
            $this->person_object = connection::get_em()->getRepository('midgard:midgard_person')->findOneBy(array('guid' => $this->person));
        }
    	return $this->person_object;
    }

    public function create()
    {
        if (empty($this->authtype))
        {
            return false;
        }
        if (!empty($this->id))
        {
            return false;
        }
        $this->guid = connection::generate_guid();
        try
        {
            connection::get_em()->persist($this);
            connection::get_em()->flush();
        }
        catch (Exception $e)
        {
            throw $e;
            var_dump($e->getMessage());
            return false;
        }
	    return ($this->id != 0);
    }

    public function update()
    {

    }

    public function delete()
    {

    }
}
?>
