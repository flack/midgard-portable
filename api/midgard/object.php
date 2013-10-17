<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\connection;
use midgard\portable\storage\metadata;
use Doctrine\ORM\Query;

abstract class midgard_object extends midgard_dbobject
{
    protected $guid = '';

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
            connection::get_em()->persist($this);
            connection::get_em()->flush();
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
        return false;
    }

    public function delete($check_dependencies = true)
    {
        if (   $check_dependencies
            && $this->has_dependents())
        {
            return false;
        }
        if (!($this instanceof metadata\entity))
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
            connection::get_em()->remove($this);
            connection::get_em()->flush();
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
        return false;
    }

    public static function new_collector()
    {
        return false;
    }

    public static function new_reflection_property()
    {
        return false;
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