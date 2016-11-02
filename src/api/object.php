<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api;

use midgard\portable\storage\connection;
use midgard\portable\storage\objectmanager;
use midgard\portable\storage\collection;
use midgard\portable\storage\metadata\entity as metadata_interface;
use midgard\portable\mgdschema\translator;
use midgard\portable\api\error\exception;
use Doctrine\ORM\Query;
use midgard_connection;
use Doctrine\Common\Persistence\Proxy;

abstract class object extends dbobject
{
    public $action = ''; // <== does this need to do anything?

    private $collections = array();

    /**
     *
     * @param mixed $id ID or GUID
     */
    public function __construct($id = null)
    {
        if ($id !== null)
        {
            if (is_int($id))
            {
                $this->get_by_id($id);
            }
            else if (is_string($id))
            {
                $this->get_by_guid($id);
            }
        }
    }

    /**
     *
     * @param string $classname
     * @return collection
     */
    private function get_collection($classname)
    {
        if (!array_key_exists($classname, $this->collections))
        {
            $this->collections[$classname] = new collection($classname);
        }
        return $this->collections[$classname];
    }

    public function __set($field, $value)
    {
        if ($field == 'guid')
        {
            return;
        }
        parent::__set($field, $value);
    }

    public function __get($field)
    {
        if (   $field === 'metadata'
            && property_exists($this, 'metadata')
            && $this->metadata === null)
        {
            $this->metadata = new metadata($this);
        }

        return parent::__get($field);
    }

    public function __call($method, $args)
    {
        if ($method === 'list')
        {
            return $this->_list();
        }
        throw new \BadMethodCallException("Unknown method " . $method . " on " . get_class($this));
    }

    protected function load_parent(array $candidates)
    {
        foreach ($candidates as $candidate)
        {
            if ($this->$candidate !== null)
            {
                //Proxies become stale if the object itself is detached, so we have to re-fetch
                if (   $this->$candidate instanceof \Doctrine\ORM\Proxy\Proxy
                    && $this->$candidate->__isInitialized())
                {
                    try
                    {
                        $this->$candidate->get_by_id($this->$candidate->id);
                    }
                    catch (exception $e)
                    {
                        connection::log()->error('Failed to refresh parent from proxy: ' . $e->getMessage());
                        return null;
                    }
                }
                return $this->$candidate;
            }
        }
        return null;
    }

    public function get_by_id($id)
    {
        $entity = connection::get_em()->find(get_class($this), $id);

        if ($entity === null)
        {
            throw exception::not_exists();
        }
        // According to Doctrine documentation, proxies should be transparent, but in practice,
        // there will be problems if we don't force-load
        if (   $entity instanceof \Doctrine\ORM\Proxy\Proxy
            && !$entity->__isInitialized())
        {
            try
            {
                $entity->__load();
            }
            catch (\Doctrine\ORM\EntityNotFoundException $e)
            {
                throw exception::object_purged();
            }
        }
        if ($entity->metadata_deleted)
        {
            // This can happen when the "deleted" entity is still in EM's identity map
            throw exception::object_deleted();
        }
        if (empty($entity->guid))
        {
            // This can happen when a reference proxy to a purged entity is still in EM's identity map
            throw exception::object_purged();
        }

        $this->populate_from_entity($entity);

        connection::get_em()->detach($entity);
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    public function get_by_guid($guid)
    {
        if (!mgd_is_guid($guid))
        {
            throw new \InvalidArgumentException("'$guid' is not a valid guid");
        }
        $entity = connection::get_em()->getRepository(get_class($this))->findOneBy(array('guid' => $guid));
        if ($entity === null)
        {
            throw exception::not_exists();
        }
        $this->populate_from_entity($entity);

        connection::get_em()->detach($entity);
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    public function create()
    {
        if (!empty($this->id))
        {
            exception::duplicate();
            return false;
        }
        if (   !$this->is_unique()
            || !$this->check_parent())
        {
            return false;
        }
        if (!$this->check_fields())
        {
            return false;
        }
        try
        {
            $om = new objectmanager(connection::get_em());
            $om->create($this);
        }
        catch (\Exception $e)
        {
            exception::internal($e);
            return false;
        }

        midgard_connection::get_instance()->set_error(MGD_ERR_OK);

        return ($this->id != 0);
    }

    public function update()
    {
        if (empty($this->id))
        {
            midgard_connection::get_instance()->set_error(MGD_ERR_INTERNAL);
            return false;
        }
        if (!$this->check_fields())
        {
            return false;
        }
        try
        {
            $om = new objectmanager(connection::get_em());
            $om->update($this);
        }
        catch (\Exception $e)
        {
            exception::internal($e);
            return false;
        }
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);

        return true;
    }

    /**
     * @todo: Tests indicate that $check_dependencies is ignored in the mgd2 extension,
     * so we might consider ignoring it, too
     */
    public function delete($check_dependencies = true)
    {
        if (empty($this->id))
        {
            midgard_connection::get_instance()->set_error(MGD_ERR_INVALID_PROPERTY_VALUE);
            return false;
        }
        if (   $check_dependencies
            && $this->has_dependents())
        {
            exception::has_dependants();
            return false;
        }
        if (!($this instanceof metadata_interface))
        {
            return $this->purge($check_dependencies);
        }
        if ($this->metadata_deleted)
        {
            return true;
        }

        try
        {
            $om = new objectmanager(connection::get_em());
            $om->delete($this);
        }
        catch (\Exception $e)
        {
            exception::internal($e);
            return false;
        }

        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    private function is_unique()
    {
        $this->initialize();

        if (empty($this->cm->midgard['unique_fields']))
        {
            return true;
        }

        $qb = connection::get_em()->createQueryBuilder();
        $qb->from(get_class($this), 'c');
        $conditions = $qb->expr()->andX();
        if ($this->id)
        {
            $parameters = array
            (
                'id' => $this->id
            );
            $conditions->add($qb->expr()->neq('c.id', ':id'));
        }
        $found = false;
        foreach ($this->cm->midgard['unique_fields'] as $field)
        {
            if (empty($this->$field))
            {
                //empty names automatically pass according to Midgard logic
                continue;
            }
            $conditions->add($qb->expr()->eq('c.' . $field, ':' . $field));
            $parameters[$field] = $this->$field;
            $found = true;
        }

        if (!$found)
        {
            return true;
        }

        if (!empty($this->cm->midgard['upfield']))
        {
            // TODO: This needs to be changed so that value is always numeric, since this is how midgard does it
            if ($this->{$this->cm->midgard['upfield']} === null)
            {
                $conditions->add($qb->expr()->isNull('c.' . $this->cm->midgard['upfield']));
            }
            else
            {
                $conditions->add($qb->expr()->eq('c.' . $this->cm->midgard['upfield'], ':' . $this->cm->midgard['upfield']));
                $parameters[$this->cm->midgard['upfield']] = $this->{$this->cm->midgard['upfield']};
            }
        }
        $qb->where($conditions)
            ->setParameters($parameters);

        $qb->select("count(c)");
        $count = intval($qb->getQuery()->getSingleScalarResult());

        if ($count !== 0)
        {
            exception::object_name_exists();
            return false;
        }
        return true;
    }

    private function check_parent()
    {
        $this->initialize();

        if (   empty($this->cm->midgard['parentfield'])
            || empty($this->cm->midgard['parent']))
        {
            return true;
        }

        if (empty($this->{$this->cm->midgard['parentfield']}))
        {
            exception::object_no_parent();
            return false;
        }
        return true;
    }

    private function check_fields()
    {
        $this->initialize();

        foreach ($this->cm->fieldMappings as $name => $field)
        {
            if (   $field['midgard:midgard_type'] == translator::TYPE_GUID
                && !empty($this->$name)
                && !mgd_is_guid($this->$name))
            {
                exception::invalid_property_value("'" . $name . "' property's value is not a guid.");
                return false;
            }
        }
        return true;
    }

    public function is_in_parent_tree($root_id, $id)
    {
        return false;
    }

    public function is_in_tree($root_id, $id)
    {
        return false;
    }

    public function has_dependents()
    {
        $this->initialize();

        $stat = false;

        if (!empty($this->cm->midgard['upfield']))
        {
            $qb = connection::get_em()->createQueryBuilder();
            $qb->from(get_class($this), 'c')
                ->where('c.' . $this->cm->midgard['upfield'] . ' = ?0')
                ->setParameter(0, $this->id)
                ->select("COUNT(c)");
            $results = intval($qb->getQuery()->getSingleScalarResult());
            $stat = ($results > 0);
        }

        if (   !$stat
            && !empty($this->cm->midgard['childtypes']))
        {
            foreach ($this->cm->midgard['childtypes'] as $typename => $parentfield)
            {
                $qb = connection::get_em()->createQueryBuilder();
                $qb->from('midgard:' . $typename, 'c')
                    ->where('c.' . $parentfield . ' = ?0')
                    ->setParameter(0, $this->id)
                    ->select("COUNT(c)");

                $results = intval($qb->getQuery()->getSingleScalarResult());
                $stat = ($results > 0);
                if ($stat)
                {
                    break;
                }
            }
        }

        return $stat;
    }

    public function get_parent()
    {
        return null;
    }

    /**
     * This function is called list() in Midgard, but that doesn't work in plain PHP
     *
     * @return array
     */
    private function _list()
    {
        $this->initialize();

        if (!empty($this->cm->midgard['upfield']))
        {
            $qb = connection::get_em()->createQueryBuilder();
            $qb->from(get_class($this), 'c')
                ->where('c.' . $this->cm->midgard['upfield'] . ' = ?0')
                ->setParameter(0, $this->id)
                ->select("c");
            return $qb->getQuery()->getResult();
        }

        return array();
    }

    /**
     * This should return child objects, but only if they are of a different type
     * For all other input, an empty array is returned
     * (not implemented yet)
     *
     * @param string $classname
     * @return array
     */
    public function list_children($classname)
    {
        return array();
    }

    public function get_by_path($path)
    {
        $parts = explode('/', trim($path, '/'));
        if (empty($parts))
        {
            return false;
        }
        $this->initialize();

        if (count($this->cm->midgard['unique_fields']) != 1)
        {
            return false;
        }

        $field = $this->cm->midgard['unique_fields'][0];

        if (!empty($this->cm->midgard['parent']))
        {
            $parent_cm = connection::get_em()->getClassMetadata('midgard:' . $this->cm->midgard['parent']);
            $parentclass = $this->cm->fullyQualifiedClassName($this->cm->midgard['parent']);
            $parentfield = $parent_cm->midgard['upfield'];
            $upfield = $this->cm->midgard['parentfield'];
        }
        else if (!empty($this->cm->midgard['upfield']))
        {
            $parentclass = get_class($this);
            $upfield = $this->cm->midgard['upfield'];
            $parentfield = $upfield;
        }
        else
        {
            return false;
        }

        $name = array_pop($parts);
        $up = 0;
        foreach ($parts as $part)
        {
            $qb = $this->get_uniquefield_query($parentclass, $field, $part, $parentfield, $up);
            $qb->select("c.id");
            $up = intval($qb->getQuery()->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR));
            if ($up === 0)
            {
                exception::not_exists();
                $this->id = 0;
                $this->set_guid('');
                return false;
            }
        }

        $qb = $this->get_uniquefield_query(get_class($this), $field, $name, $upfield, $up);
        $qb->select("c");

        $entity = $qb->getQuery()->getOneOrNullResult();

        if ($entity === null)
        {
            exception::not_exists();
            $this->id = 0;
            $this->set_guid('');
            return false;
        }
        $this->populate_from_entity($entity);

        return true;
    }

    /**
     * @return int
     */
    protected function get_uniquefield_query($classname, $field, $part, $upfield, $up)
    {
        $qb = connection::get_em()->createQueryBuilder();
        $qb->from($classname, 'c');
        $conditions = $qb->expr()->andX();
        $conditions->add($qb->expr()->eq('c.' . $field, ':' . $field));
        $parameters = array
        (
            $field => $part
        );

        if (empty($up))
        {
            // If the database was created by Midgard, it might contain 0 instead of NULL, so...
            $empty_conditions = $qb->expr()->orX()
                ->add($qb->expr()->isNull('c.' . $upfield))
                ->add($qb->expr()->eq('c.' . $upfield, '0'));
            $conditions->add($empty_conditions);
        }
        else
        {
            $conditions->add($qb->expr()->eq('c.' . $upfield, ':' . $upfield));
            $parameters[$upfield] = $up;
        }

        $qb->where($conditions)
            ->setParameters($parameters);

        return $qb;
    }

    public function parent()
    {
        return false;
    }

    public function has_parameters()
    {
        return $this->get_collection('midgard_parameter')->is_empty($this->guid);
    }

    public function list_parameters($domain = false)
    {
        $constraints = array();
        if ($domain)
        {
            $constraints[] = array("domain", "=", $domain);
        }

        return $this->get_collection('midgard_parameter')->find($this->guid, $constraints);
    }

    public function find_parameters(array $constraints = array())
    {
        return $this->get_collection('midgard_parameter')->find($this->guid, $constraints);
    }

    public function delete_parameters(array $constraints = array())
    {
        return $this->get_collection('midgard_parameter')->delete($this->guid, $constraints);
    }

    public function purge_parameters(array $constraints = array())
    {
        return $this->get_collection('midgard_parameter')->purge($this->guid, $constraints);
    }

    public function get_parameter($domain, $name)
    {
        if (!$this->guid)
        {
            return false;
        }
        $qb = connection::get_em()->createQueryBuilder();
        $qb
            ->select('c.value')
            ->from('midgard:midgard_parameter', 'c')
            ->where('c.domain = :domain AND c.name = :name AND c.parentguid = :parentguid')
            ->setParameters(array('domain' => $domain, 'name' => $name, 'parentguid' => $this->guid));

        // workaround for http://www.doctrine-project.org/jira/browse/DDC-2655
        try
        {
            return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR);
        }
        catch (\Doctrine\ORM\NoResultException $e)
        {
            return null;
        }
    }

    public function set_parameter($domain, $name, $value)
    {
        $constraints = array
        (
            array ('domain', '=', $domain),
            array ('name', '=', $name),
        );
        $params = $this->get_collection('midgard_parameter')->find($this->guid, $constraints);

        // check value
        if ($value === false || $value === null || $value === "")
        {
            if (count($params) == 0)
            {
                exception::not_exists();
                return false;
            }
            foreach ($params as $param)
            {
                $stat = $param->delete();
            }
            return $stat;
        }

        $om = new objectmanager(connection::get_em());
        try
        {
            // create new
            if (count($params) == 0)
            {
                $parameter = $om->new_instance(connection::get_em()->getClassMetadata('midgard:midgard_parameter')->getName());
                $parameter->parentguid = $this->guid;
                $parameter->domain = $domain;
                $parameter->name = $name;
                $parameter->value = $value;
                $om->create($parameter);
            }
            // use existing
            else
            {
                $parameter = array_shift($params);
                $parameter->value = $value;
                $om->update($parameter);
            }
            midgard_connection::get_instance()->set_error(MGD_ERR_OK);
            return true;
        }
        catch (\Exception $e)
        {
            exception::internal($e);
            return false;
        }
    }

    /**
     * The signature is a little different from original, because Doctrine doesn't support func_get_args() in proxies
     */
    public function parameter($domain, $name, $value = '__UNINITIALIZED__')
    {
        if ($value === '__UNINITIALIZED__')
        {
            return $this->get_parameter($domain, $name);
        }
        return $this->set_parameter($domain, $name, $value);
    }

    public function has_attachments()
    {
        return $this->get_collection('midgard_attachment')->is_empty($this->guid);
    }

    public function list_attachments()
    {
        return $this->get_collection('midgard_attachment')->find($this->guid, array());
    }

    public function find_attachments(array $constraints = array())
    {
        return $this->get_collection('midgard_attachment')->find($this->guid, $constraints);
    }

    public function delete_attachments(array $constraints = array())
    {
        return $this->get_collection('midgard_attachment')->delete($this->guid, $constraints);
    }

    /**
     *
     * @param array $constraints
     * @param boolean $delete_blob
     * @return boolean False if one or more attachments couldn't be deleted
     * @todo Implement delete_blob & return value
     */
    public function purge_attachments(array $constraints = array(), $delete_blob = true)
    {
        return $this->get_collection('midgard_attachment')->purge($this->guid, $constraints);
    }

    public function create_attachment($name, $title = '', $mimetype = '')
    {
        $existing = $this->get_collection('midgard_attachment')->find($this->guid, array('name' => $name));
        if (count($existing) > 0)
        {
            exception::object_name_exists();
            return null;
        }
        $om = new objectmanager(connection::get_em());
        $att = $om->new_instance(connection::get_em()->getClassMetadata('midgard:midgard_attachment')->getName());

        $att->parentguid = $this->guid;
        $att->title = $title;
        $att->name = $name;
        $att->mimetype = $mimetype;
        try
        {
            $om->create($att);
            midgard_connection::get_instance()->set_error(MGD_ERR_OK);
            return $att;
        }
        catch (\Exception $e)
        {
            exception::internal($e);
            return null;
        }
    }

    public static function serve_attachment($guid)
    {
        return false;
    }

    /**
     * @todo: Tests indicate that $check_dependencies is ignored in the mgd2 extension,
     * so we might consider ignoring it, too
     */
    public function purge($check_dependencies = true)
    {
        if (empty($this->id))
        {
            // This usually means that the object has been purged already
            exception::not_exists();
            return false;
        }
        if (   $check_dependencies
            && $this->has_dependents())
        {
            exception::has_dependants();
            return false;
        }

        try
        {
            $om = new objectmanager(connection::get_em());
            $om->purge($this);
        }
        catch (\Doctrine\ORM\EntityNotFoundException $e)
        {
            exception::not_exists();
            return false;
        }
        catch (\Exception $e)
        {
            exception::internal($e);
            return false;
        }
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);

        return true;
    }

    public static function undelete($guid)
    {
        return \midgard_object_class::undelete($guid);
    }

    public function connect($signal, $callback, $user_data)
    {
        return false;
    }

    public static function new_query_builder()
    {
        return new \midgard_query_builder(get_called_class());
    }

    public static function new_collector($field, $value)
    {
        return new \midgard_collector(get_called_class(), $field, $value);
    }

    public static function new_reflection_property()
    {
        return new \midgard_reflection_property(get_called_class());
    }

    public function set_guid($guid)
    {
        parent::__set('guid', $guid);
    }

    public function emit($signal)
    {
        return false;
    }

    /**
     * Helper for managing the isapproved and islocked metadata properties
     *
     * @param string $action the property to manage (either approve or lock)
     * @param bool $value
     * @return boolean
     */
    private function manage_meta_property($action, $value)
    {
        $user = connection::get_user();
        if ($user === null)
        {
            exception::access_denied();
            return false;
        }
        if ($action == 'lock')
        {
            $flag = 'islocked';
        }
        else if ($action == 'approve')
        {
            $flag = 'isapproved';
        }
        else
        {
            throw new exception('Unsupported action ' . $action);
        }
        // same val
        if ($this->__get('metadata')->$flag === $value)
        {
            return false;
        }
        if ($value === false)
        {
            $action = 'un' . $action;
        }

        if ($this->id)
        {
            try
            {
                $om = new objectmanager(connection::get_em());
                $om->{$action}($this);
            }
            catch (\Exception $e)
            {
                exception::internal($e);
                return false;
            }
        }
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);

        return true;
    }

    public function approve()
    {
       return $this->manage_meta_property("approve", true);
    }

    public function is_approved()
    {
        return $this->metadata_isapproved;
    }

    public function unapprove()
    {
        return $this->manage_meta_property("approve", false);
    }

    public function lock()
    {
        if ($this->is_locked())
        {
            exception::object_is_locked();
            return false;
        }
        return $this->manage_meta_property("lock", true);
    }

    public function is_locked()
    {
        return $this->metadata_islocked;
    }

    public function unlock()
    {
        return $this->manage_meta_property("lock", false);
    }

    public function get_workspace()
    {
        return false;
    }
}
