<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api;

use midgard\portable\storage\connection;
use midgard\portable\storage\objectmanager;
use midgard\portable\storage\metadata\entity as metadata_interface;
use midgard\portable\api\metadata;
use midgard\portable\api\error\exception;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Query;

abstract class object extends dbobject
{
    public $action = ''; // <== does this need to do anything?

    protected $schema_type;

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

    protected function load_parent(array $candidates)
    {
        foreach ($candidates as $candidate)
        {
            if ($this->$candidate !== null)
            {
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
        \midgard_connection::get_instance()->set_error(MGD_ERR_OK);
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
        return true;
    }

    public function update()
    {
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
        \midgard_connection::get_instance()->set_error(MGD_ERR_OK);

        return true;
    }

    public function create()
    {
        if (   !empty($this->id)
            || !$this->is_unique()
            || !$this->check_parent())
        {
            return false;
        }
        $this->guid = connection::generate_guid();
        try
        {
            connection::get_em()->persist($this);
            connection::get_em()->flush($this);
        }
        catch (\Exception $e)
        {
            exception::internal($e);
            return false;
        }
        \midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return ($this->id != 0);
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

    private function apply_qb_constraints($qb, array $constraints)
    {
        foreach ($constraints as $constraint)
        {
            $qb->add_constraint($constraint[0], $constraint[1], $constraint[2]);
        }
        return $qb;
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

    public function delete($check_dependencies = true)
    {
        if (   $check_dependencies
            && $this->has_dependents())
        {
            exception::has_dependants();
            return false;
        }
        if (!($this instanceof metadata_interface))
        {
            return $this->purge();
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

        \midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    public function get_parent()
    {
        return null;
    }

    public function list_children($node, $class, $name)
    {
        return false;
    }

    public function get_by_path($path)
    {
        $parts = explode('/', trim($path, '/'));
        if (empty($parts))
        {
            return false;
        }
        $this->initialize();

        if (   count($this->cm->midgard['unique_fields']) != 1
            || empty($this->cm->midgard['upfield']))
        {
            return false;
        }
        $field = $this->cm->midgard['unique_fields'][0];
        $upfield = $this->cm->midgard['upfield'];

        $name = array_pop($parts);
        $up = 0;
        foreach ($parts as $part)
        {
            $qb = $this->get_uniquefield_query($field, $part, $upfield, $up);
            $qb->select("c.id");
            $up = intval($qb->getQuery()->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR));
            if ($up === 0)
            {
                exception::not_exists();
                $this->id = 0;
                $this->guid = '';
                return false;
            }
        }

        $qb = $this->get_uniquefield_query($field, $name, $upfield, $up);
        $qb->select("c");
        $entity = $qb->getQuery()->getOneOrNullResult();

        if ($entity === null)
        {
            exception::not_exists();
            $this->id = 0;
            $this->guid = '';
            return false;
        }
        $this->populate_from_entity($entity);

        return true;
    }

    /**
     * @return int
     */
    protected function get_uniquefield_query($field, $part, $upfield, $up)
    {
        $qb = connection::get_em()->createQueryBuilder();
        $qb->from(get_class($this), 'c');
        $conditions = $qb->expr()->andX();
        $conditions->add($qb->expr()->eq('c.' . $field, ':' . $field));
        $parameters = array
        (
            $field => $part
        );

        if (empty($up))
        {
            $conditions->add($qb->expr()->isNull('c.' . $upfield));
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

    private function get_parameter_qb()
    {
        $qb = new \midgard_query_builder('midgard:midgard_parameter');
        $qb->add_constraint('parentguid', '=', $this->guid);
        return $qb;
    }

    public function has_parameters()
    {
        $qb = $this->get_parameter_qb();
        return ($qb->count() > 0);
    }

    public function list_parameters($domain = false)
    {
        $qb = $this->get_parameter_qb();
        if ($domain)
        {
            $qb->add_constraint("domain", "=", $domain);
        }
        return $qb->execute();
    }

    public function find_parameters(array $constraints = array())
    {
        $qb = $this->get_parameter_qb();
        $this->apply_qb_constraints($qb, $constraints);
        return $qb->execute();
    }

    public function delete_parameters(array $constraints = array())
    {
        $qb = $this->get_parameter_qb();
        $this->apply_qb_constraints($qb, $constraints);
        $params = $qb->execute();
        $deleted_count = 0;
        foreach ($params as $param)
        {
            if ($param->delete())
            {
                $deleted_count++;
            }
        }
        return $deleted_count;
    }

    public function purge_parameters(array $constraints = array())
    {
        $qb = $this->get_parameter_qb();
        $this->apply_qb_constraints($qb, $constraints);
        $params = $qb->execute();
        $purged_count = 0;
        foreach ($params as $param)
        {
            if ($param->purge())
            {
                $purged_count++;
            }
        }
        return $purged_count;
    }

    public function get_parameter($domain, $name)
    {
        if (!$this->guid)
        {
            return false;
        }
        $qb = $this->get_parameter_qb();
        $qb->add_constraint("domain", "=", $domain);
        $qb->add_constraint("name", "=", $name);
        $params = $qb->execute();
        if (count($params) == 0)
        {
            return null;
        }
        $param = array_shift($params);
        return $param->value;
    }

    public function set_parameter($domain, $name, $value)
    {
        $qb = $this->get_parameter_qb();
        $qb->add_constraint("domain", "=", $domain);
        $qb->add_constraint("name", "=", $name);
        $params = $qb->execute();

        // check value
        if ($value === false || $value === null || $value === "")
        {
            if (count($params) > 0)
            {
                foreach ($params as $param)
                {
                    $param->delete();
                }
            }
            return true;
        }

        // create new
        if (count($params) == 0)
        {
            $parameter = $this->get_entity_instance("midgard_parameter");
            $parameter->parentguid = $this->guid;
            $parameter->domain = $domain;
            $parameter->name = $name;
        }
        // use existing
        else
        {
            $parameter = array_shift($params);
        }

        // update the value
        $parameter->value = $value;

        $em = connection::get_em();
        $em->persist($parameter);
        $em->flush($parameter);

        return true;
    }

    public function parameter($domain, $name)
    {
        if (func_num_args() == 2)
        {
            return $this->get_parameter($domain, $name);
        }
        else
        {
            $value = func_get_arg(2);
            return $this->set_parameter($domain, $name, $value);
        }
    }

    public function has_attachments()
    {
        return false;
    }

    public function list_attachments()
    {
        return false;
    }

    public function find_attachments($constraints)
    {
        return false;
    }

    public function delete_attachments($constraints)
    {
        return false;
    }

    public function purge_attachments($constraints, $delete_blob)
    {
        return false;
    }

    public function create_attachment($name, $title, $mimetype)
    {
        return false;
    }

    public static function serve_attachment($guid)
    {
        return false;
    }

    /**
     * @todo: What is the default for check_dependencies and what does it do?
     */
    public function purge($check_dependencies = false)
    {
        if (empty($this->id))
        {
            // This usually means that the object has been purged already
            exception::not_exists();
            return false;
        }
        try
        {
            $om = new objectmanager(connection::get_em());
            $om->purge($this);
        }
        catch (\Exception $e)
        {
            exception::internal($e);
            return false;
        }
        \midgard_connection::get_instance()->set_error(MGD_ERR_OK);

        return true;
    }

    public static function undelete($guid)
    {
        return false;
    }

    public function connect($signal, $callback, $user_data)
    {
        return false;
    }

    public static function new_query_builder()
    {
        return new \midgard_collector(get_called_class());
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
        return false;
    }

    public function emit($signal)
    {
        return false;
    }

    public function approve()
    {
        return false;
    }

    public function is_approved()
    {
        return false;
    }

    public function unapprove()
    {
        return false;
    }

    public function lock()
    {
        return false;
    }

    public function is_locked()
    {
        return false;
    }

    public function unlock()
    {
        return false;
    }

    public function get_workspace()
    {
        return false;
    }
}
?>