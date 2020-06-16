<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\api;

use midgard\portable\storage\connection;
use midgard\portable\storage\objectmanager;
use midgard\portable\api\error\exception;
use midgard_connection;

class user extends dbobject
{
    private $person_object;

    protected $id;

    protected $properties = [];

    protected $person;

    protected $guid = '';

    protected $login = '';

    protected $password = '';

    protected $active = false;

    protected $authtype = '';

    protected $authtypeid = 0;

    protected $usertype = 0;

    public function __construct(array $properties = [])
    {
        if (!empty($properties)) {
            $this->load_by_properties($properties);
        }
    }

    public function __set($field, $value)
    {
        if ($field == 'guid') {
            return;
        }
        parent::__set($field, $value);
    }

    private function load_by_properties(array $properties)
    {
        if (   !array_key_exists('authtype', $properties)
            || !array_key_exists('login', $properties)) {
            throw exception::invalid_property_value();
        }
        $entity = connection::get_em()->getRepository('midgard:midgard_user')->findOneBy($properties);

        if ($entity === null) {
            throw exception::not_exists();
        }
        $this->populate_from_entity($entity);
    }

    public function login() : bool
    {
        if (empty($this->id)) {
            return false;
        }
        connection::set_user($this);
        return true;
    }

    public function logout() : bool
    {
        if (empty($this->id)) {
            return false;
        }
        connection::set_user(null);
        return true;
    }

    public function is_admin() : bool
    {
        return $this->usertype == 2;
    }

    public function set_person(person $person)
    {
        $this->person_object = $person;
        $this->person = $person->guid;
    }

    public function &get_person() : person
    {
        if (   $this->person_object === null
            && $this->person !== null) {
            $this->person_object = connection::get_em()->getRepository('midgard:midgard_person')->findOneBy(['guid' => $this->person]);
        }
        return $this->person_object;
    }

    public function create() : bool
    {
        if (   empty($this->authtype)
            || !empty($this->id)) {
            exception::invalid_property_value();
            return false;
        }
        if (!$this->is_unique()) {
            exception::duplicate();
            return false;
        }
        $this->guid = connection::generate_guid();
        try {
            $om = new objectmanager(connection::get_em());
            $om->create($this);
        } catch (\Exception $e) {
            exception::internal($e);
            return false;
        }

        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return !empty($this->id);
    }

    public function update() : bool
    {
        if (empty($this->id) || !mgd_is_guid($this->guid)) {
            exception::invalid_property_value();
            return false;
        }
        if (!$this->is_unique()) {
            exception::duplicate();
            return false;
        }
        try {
            $om = new objectmanager(connection::get_em());
            $om->update($this);
        } catch (\Exception $e) {
            exception::internal($e);
            return false;
        }
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    public function delete() : bool
    {
        if (!mgd_is_guid($this->guid)) {
            exception::invalid_property_value();
            return false;
        }

        try {
            $om = new objectmanager(connection::get_em());
            $om->purge($this);
        } catch (\Exception $e) {
            exception::internal($e);
            return false;
        }
        $this->guid = '';
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    protected function is_unique() : bool
    {
        if (   empty($this->login)
            || empty($this->authtype)) {
            return true;
        }

        $qb = connection::get_em()->createQueryBuilder();
        $qb->from(get_class($this), 'c');
        $conditions = $qb->expr()->andX();
        $parameters = [
            'login' => $this->login,
            'authtype' => $this->authtype
        ];

        if ($this->id) {
            $parameters['id'] = $this->id;
            $conditions->add($qb->expr()->neq('c.id', ':id'));
        }
        $conditions->add($qb->expr()->eq('c.login', ':login'));
        $conditions->add($qb->expr()->eq('c.authtype', ':authtype'));

        $qb->where($conditions)
            ->setParameters($parameters);

        $qb->select("count(c)");
        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count === 0;
    }
}
