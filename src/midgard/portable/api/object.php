<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api;

use midgard\portable\storage\connection;
use midgard\portable\storage\metadata\entity as metadata_interface;
use midgard\portable\api\metadata;
use Doctrine\Common\Persistence\ObjectManagerAware;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Query;

abstract class object extends dbobject
{
    protected $guid = '';

    protected $action = ''; // <== does this need to do anything?

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
            throw new \midgard_error_exception('cannot load object ' . $id);
        }
        $this->populate_from_entity($entity);
        return $this; // <== is this right?
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
            throw new \midgard_error_exception('cannot load object ' . $guid);
        }
        $this->populate_from_entity($entity);
        return $this; // <== is this right?
    }

    public function update()
    {
        try
        {
            if (!connection::get_em()->contains($this))
            {
                $entity = connection::get_em()->merge($this);
                connection::get_em()->persist($entity);
                connection::get_em()->flush($entity);
            }
            else
            {
                connection::get_em()->persist($this);
                connection::get_em()->flush($this);
            }
        }
        catch (Exception $e)
        {
            throw $e;
            var_dump($e->getMessage());
            return false;
        }
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
        catch (Exception $e)
        {
            throw $e;
            var_dump($e->getMessage());
            return false;
        }
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
        foreach ($this->cm->midgard['unique_fields'] as $field)
        {
            $conditions->add($qb->expr()->eq('c.' . $field, ':' . $field));
            $parameters[$field] = $this->$field;
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

        return ($count === 0);
    }

    private function check_parent()
    {
        $this->initialize();

        if (   empty($this->cm->midgard['parentfield'])
            || empty($this->cm->midgard['parent']))
        {
            return true;
        }

        return (!empty($this->{$this->cm->midgard['parentfield']}));
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
            \midgard_connection::get_instance()->set_error(MGD_ERR_HAS_DEPENDANTS);
            return false;
        }
        if (!($this instanceof metadata_interface))
        {
            return $this->purge();
        }
        $this->metadata_deleted = true;
        return $this->update();
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
        return new static();
    }

    public function parent()
    {
        return false;
    }

    public function has_parameters()
    {
        return false;
    }

    public function list_parameters($domain)
    {
        return false;
    }

    public function find_parameters($constraints)
    {
        return false;
    }

    public function delete_parameters($constraints)
    {
        return false;
    }

    public function purge_parameters($constraints)
    {
        return false;
    }

    public function get_parameter($domainname)
    {
        return false;
    }

    public function set_parameter($domainname, $value)
    {
        return false;
    }

    public function parameter()
    {
        return false;
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
        try
        {
            if (!connection::get_em()->contains($this))
            {
                $entity = connection::get_em()->merge($this);
                connection::get_em()->remove($entity);
                connection::get_em()->flush($entity);
            }
            else
            {
                connection::get_em()->remove($this);
                connection::get_em()->flush($this);
            }
        }
        catch (Exception $e)
        {
            throw $e;
            var_dump($e->getMessage());
            return false;
        }
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