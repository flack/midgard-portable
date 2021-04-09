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
use midgard\portable\storage\interfaces\metadata as metadata_interface;
use midgard\portable\mgdschema\translator;
use midgard\portable\api\error\exception;
use Doctrine\ORM\Query;
use midgard_connection;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\ORM\QueryBuilder;

/**
 * @property metadata $metadata
 */
abstract class mgdobject extends dbobject
{
    protected $metadata; // compat with mgd behavior: If the schema has no metadata, the property is present anyway

    public $action = ''; // <== does this need to do anything?

    private $collections = [];

    /**
     * @param mixed $id ID or GUID
     */
    public function __construct($id = null)
    {
        if ($id !== null) {
            if (is_int($id)) {
                $this->get_by_id($id);
            } elseif (is_string($id)) {
                $this->get_by_guid($id);
            }
        }
    }

    private function get_collection(string $classname) : collection
    {
        if (!isset($this->collections[$classname])) {
            $this->collections[$classname] = new collection($classname);
        }
        return $this->collections[$classname];
    }

    public function __debugInfo()
    {
        $ret = parent::__debugInfo();
        if (property_exists($this, 'metadata')) {
            $metadata = new \stdClass;
            foreach ($this->cm->getFieldNames() as $name) {
                if (strpos($name, 'metadata_') !== false) {
                    $fieldname = str_replace('metadata_', '', $name);
                    $metadata->$fieldname = $this->__get($name);
                }
            }
            $ret['metadata'] = $metadata;
        }

        return $ret;
    }

    public function __set($field, $value)
    {
        if ($field == 'guid') {
            return;
        }
        parent::__set($field, $value);
    }

    public function __get($field)
    {
        if (   $field === 'metadata'
            && $this->metadata === null
            && $this instanceof metadata_interface) {
            $this->metadata = new metadata($this);
        }

        return parent::__get($field);
    }

    public function __call($method, $args)
    {
        if ($method === 'list') {
            return $this->_list();
        }
        throw new \BadMethodCallException("Unknown method " . $method . " on " . get_class($this));
    }

    protected function load_parent(array $candidates) : ?dbobject
    {
        foreach ($candidates as $candidate) {
            if ($this->$candidate !== null) {
                //Proxies become stale if the object itself is detached, so we have to re-fetch
                if (   $this->$candidate instanceof Proxy
                    && $this->$candidate->__isInitialized()) {
                    try {
                        $this->$candidate->get_by_id($this->$candidate->id);
                    } catch (exception $e) {
                        connection::log()->error('Failed to refresh parent from proxy: ' . $e->getMessage());
                        return null;
                    }
                }
                return $this->$candidate;
            }
        }
        return null;
    }

    public function get_by_id(int $id) : bool
    {
        $entity = connection::get_em()->find(get_class($this), $id);

        if ($entity === null) {
            throw exception::not_exists();
        }
        // According to Doctrine documentation, proxies should be transparent, but in practice,
        // there will be problems if we don't force-load
        if (   $entity instanceof Proxy
            && !$entity->__isInitialized()) {
            try {
                $entity->__load();
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                throw exception::object_purged();
            }
        }
        if ($entity instanceof metadata_interface && $entity->{metadata_interface::DELETED_FIELD}) {
            // This can happen when the "deleted" entity is still in EM's identity map
            throw exception::object_deleted();
        }
        if (empty($entity->guid)) {
            // This can happen when a reference proxy to a purged entity is still in EM's identity map
            throw exception::object_purged();
        }

        $this->populate_from_entity($entity);

        connection::get_em()->detach($entity);
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    public function get_by_guid(string $guid) : bool
    {
        if (!mgd_is_guid($guid)) {
            throw new \InvalidArgumentException("'$guid' is not a valid guid");
        }
        $entity = connection::get_em()->getRepository(get_class($this))->findOneBy(['guid' => $guid]);
        if ($entity === null) {
            throw exception::not_exists();
        }
        $this->populate_from_entity($entity);

        connection::get_em()->detach($entity);
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    public function create() : bool
    {
        if (!empty($this->id)) {
            exception::duplicate();
            return false;
        }
        if (   !$this->is_unique()
            || !$this->check_parent()) {
            return false;
        }
        if (!$this->check_fields()) {
            return false;
        }
        try {
            $om = new objectmanager(connection::get_em());
            $om->create($this);
        } catch (\Exception $e) {
            exception::internal($e);
            return false;
        }

        midgard_connection::get_instance()->set_error(MGD_ERR_OK);

        return $this->id != 0;
    }

    public function update() : bool
    {
        if (empty($this->id)) {
            midgard_connection::get_instance()->set_error(MGD_ERR_INTERNAL);
            return false;
        }
        if (!$this->check_fields()) {
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

    /**
     * @todo: Tests indicate that $check_dependencies is ignored in the mgd2 extension,
     * so we might consider ignoring it, too
     */
    public function delete(bool $check_dependencies = true) : bool
    {
        if (empty($this->id)) {
            midgard_connection::get_instance()->set_error(MGD_ERR_INVALID_PROPERTY_VALUE);
            return false;
        }
        if (   $check_dependencies
            && $this->has_dependents()) {
            exception::has_dependants();
            return false;
        }
        if (!($this instanceof metadata_interface)) {
            exception::invalid_property_value();
            return false;
        }
        if ($this->{metadata_interface::DELETED_FIELD}) {
            return true;
        }

        try {
            $om = new objectmanager(connection::get_em());
            $om->delete($this);
        } catch (\Exception $e) {
            exception::internal($e);
            return false;
        }

        midgard_connection::get_instance()->set_error(MGD_ERR_OK);
        return true;
    }

    private function is_unique() : bool
    {
        $this->initialize();

        if (empty($this->cm->midgard['unique_fields'])) {
            return true;
        }

        $qb = connection::get_em()->createQueryBuilder();
        $qb->from(get_class($this), 'c');
        $conditions = $qb->expr()->andX();
        if ($this->id) {
            $parameters = [
                'id' => $this->id
            ];
            $conditions->add($qb->expr()->neq('c.id', ':id'));
        }
        $found = false;
        foreach ($this->cm->midgard['unique_fields'] as $field) {
            if (empty($this->$field)) {
                //empty names automatically pass according to Midgard logic
                continue;
            }
            $conditions->add($qb->expr()->eq('c.' . $field, ':' . $field));
            $parameters[$field] = $this->$field;
            $found = true;
        }

        if (!$found) {
            return true;
        }

        foreach (['upfield', 'parentfield'] as $candidate) {
            if (!empty($this->cm->midgard[$candidate])) {
                // TODO: This needs to be changed so that value is always numeric, since this is how midgard does it
                if ($this->{$this->cm->midgard[$candidate]} === null) {
                    $conditions->add($qb->expr()->isNull('c.' . $this->cm->midgard[$candidate]));
                } else {
                    $conditions->add($qb->expr()->eq('c.' . $this->cm->midgard[$candidate], ':' . $this->cm->midgard[$candidate]));
                    $parameters[$this->cm->midgard[$candidate]] = $this->{$this->cm->midgard[$candidate]};
                }
                break;
            }
        }

        $qb->where($conditions)
            ->setParameters($parameters);

        $qb->select("count(c)");
        $count = (int) $qb->getQuery()->getSingleScalarResult();

        if ($count !== 0) {
            exception::object_name_exists();
            return false;
        }
        return true;
    }

    private function check_parent() : bool
    {
        $this->initialize();

        if (   empty($this->cm->midgard['parentfield'])
            || empty($this->cm->midgard['parent'])) {
            return true;
        }

        if (empty($this->{$this->cm->midgard['parentfield']})) {
            exception::object_no_parent();
            return false;
        }
        return true;
    }

    private function check_fields() : bool
    {
        $this->initialize();

        foreach ($this->cm->fieldMappings as $name => $field) {
            if (   $field['midgard:midgard_type'] == translator::TYPE_GUID
                && !empty($this->$name)
                && !mgd_is_guid($this->$name)) {
                exception::invalid_property_value("'" . $name . "' property's value is not a guid.");
                return false;
            }
        }
        return $this->check_upfield();
    }

    private function check_upfield() : bool
    {
        if (   !empty($this->id)
            && !empty($this->cm->midgard['upfield'])
            && $this->__get($this->cm->midgard['upfield']) === $this->id
            && $this->cm->getAssociationMapping($this->cm->midgard['upfield'])['targetEntity'] === $this->cm->getName()) {
            exception::tree_is_circular();
            return false;
        }
        // @todo this should be recursive
        return true;
    }

    public function is_in_parent_tree($root_id, $id) : bool
    {
        return false;
    }

    public function is_in_tree($root_id, $id) : bool
    {
        return false;
    }

    public function has_dependents() : bool
    {
        $this->initialize();

        $stat = false;

        if (!empty($this->cm->midgard['upfield'])) {
            $qb = connection::get_em()->createQueryBuilder();
            $qb->from(get_class($this), 'c')
                ->where('c.' . $this->cm->midgard['upfield'] . ' = ?0')
                ->setParameter(0, $this->id)
                ->select("COUNT(c)");
            $results = (int) $qb->getQuery()->getSingleScalarResult();
            $stat = $results > 0;
        }

        if (   !$stat
            && !empty($this->cm->midgard['childtypes'])) {
            foreach ($this->cm->midgard['childtypes'] as $typename => $parentfield) {
                $qb = connection::get_em()->createQueryBuilder();
                $qb->from('midgard:' . $typename, 'c')
                    ->where('c.' . $parentfield . ' = ?0')
                    ->setParameter(0, $this->id)
                    ->select("COUNT(c)");

                $results = (int) $qb->getQuery()->getSingleScalarResult();
                $stat = $results > 0;
                if ($stat) {
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
     */
    private function _list() : array
    {
        $this->initialize();

        if (!empty($this->cm->midgard['upfield'])) {
            $qb = connection::get_em()->createQueryBuilder();
            $qb->from(get_class($this), 'c')
                ->where('c.' . $this->cm->midgard['upfield'] . ' = ?0')
                ->setParameter(0, $this->id)
                ->select("c");
            return $qb->getQuery()->getResult();
        }

        return [];
    }

    /**
     * This should return child objects, but only if they are of a different type
     * For all other input, an empty array is returned
     * (not implemented yet)
     */
    public function list_children(string $classname) : array
    {
        return [];
    }

    public function get_by_path(string $path) : bool
    {
        $parts = explode('/', trim($path, '/'));
        if (empty($parts)) {
            return false;
        }
        $this->initialize();

        if (count($this->cm->midgard['unique_fields']) != 1) {
            return false;
        }

        $field = $this->cm->midgard['unique_fields'][0];

        if (!empty($this->cm->midgard['parent'])) {
            $parent_cm = connection::get_em()->getClassMetadata('midgard:' . $this->cm->midgard['parent']);
            $parentclass = $this->cm->fullyQualifiedClassName($this->cm->midgard['parent']);
            $parentfield = $parent_cm->midgard['upfield'];
            $upfield = $this->cm->midgard['parentfield'];
        } elseif (!empty($this->cm->midgard['upfield'])) {
            $parentclass = get_class($this);
            $upfield = $this->cm->midgard['upfield'];
            $parentfield = $upfield;
        } else {
            return false;
        }

        $name = array_pop($parts);
        $up = 0;
        foreach ($parts as $part) {
            $qb = $this->get_uniquefield_query($parentclass, $field, $part, $parentfield, $up);
            $qb->select("c.id");
            $up = (int) $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR);
            if ($up === 0) {
                exception::not_exists();
                $this->id = 0;
                $this->set_guid('');
                return false;
            }
        }

        $qb = $this->get_uniquefield_query(get_class($this), $field, $name, $upfield, $up);
        $qb->select("c");

        $entity = $qb->getQuery()->getOneOrNullResult();

        if ($entity === null) {
            exception::not_exists();
            $this->id = 0;
            $this->set_guid('');
            return false;
        }
        $this->populate_from_entity($entity);

        return true;
    }

    protected function get_uniquefield_query(string $classname, string $field, string $part, string $upfield, int $up) : QueryBuilder
    {
        $qb = connection::get_em()->createQueryBuilder();
        $qb->from($classname, 'c');
        $conditions = $qb->expr()->andX();
        $conditions->add($qb->expr()->eq('c.' . $field, ':' . $field));
        $parameters = [
            $field => $part
        ];

        if (empty($up)) {
            // If the database was created by Midgard, it might contain 0 instead of NULL, so...
            $empty_conditions = $qb->expr()->orX()
                ->add($qb->expr()->isNull('c.' . $upfield))
                ->add($qb->expr()->eq('c.' . $upfield, '0'));
            $conditions->add($empty_conditions);
        } else {
            $conditions->add($qb->expr()->eq('c.' . $upfield, ':' . $upfield));
            $parameters[$upfield] = $up;
        }

        $qb->where($conditions)
            ->setParameters($parameters);

        return $qb;
    }

    /**
     * @return boolean
     */
    public function parent()
    {
        return false;
    }

    public function has_parameters() : bool
    {
        return !$this->get_collection('midgard_parameter')->is_empty($this->guid);
    }

    public function list_parameters(string $domain = null) : array
    {
        $constraints = [];
        if ($domain) {
            $constraints = ["domain" => $domain];
        }

        return $this->get_collection('midgard_parameter')->find($this->guid, $constraints);
    }

    public function find_parameters(array $constraints = []) : array
    {
        return $this->get_collection('midgard_parameter')->find($this->guid, $constraints);
    }

    public function delete_parameters(array $constraints = []) : int
    {
        return $this->get_collection('midgard_parameter')->delete($this->guid, $constraints);
    }

    public function purge_parameters(array $constraints = []) : int
    {
        return $this->get_collection('midgard_parameter')->purge($this->guid, $constraints);
    }

    public function get_parameter(string $domain, string $name)
    {
        if (!$this->guid) {
            return false;
        }
        $qb = connection::get_em()->createQueryBuilder();
        $qb
            ->select('c.value')
            ->from('midgard:midgard_parameter', 'c')
            ->where('c.domain = :domain AND c.name = :name AND c.parentguid = :parentguid')
            ->setParameters(['domain' => $domain, 'name' => $name, 'parentguid' => $this->guid]);

        return $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR);
    }

    public function set_parameter(string $domain, string $name, $value) : bool
    {
        $constraints = [
            'domain' => $domain,
            'name' => $name,
        ];
        $params = $this->get_collection('midgard_parameter')->find($this->guid, $constraints);

        // check value
        if (in_array($value, [false, null, ''], true)) {
            if (empty($params)) {
                exception::not_exists();
                return false;
            }
            foreach ($params as $param) {
                $stat = $param->delete();
            }
            return $stat;
        }

        $om = new objectmanager(connection::get_em());
        try {
            // create new
            if (empty($params)) {
                $parameter = $om->new_instance(connection::get_em()->getClassMetadata('midgard:midgard_parameter')->getName());
                $parameter->parentguid = $this->guid;
                $parameter->domain = $domain;
                $parameter->name = $name;
                $parameter->value = $value;
                $om->create($parameter);
            }
            // use existing
            else {
                $parameter = $params[0];
                $parameter->value = $value;
                $om->update($parameter);
            }
            midgard_connection::get_instance()->set_error(MGD_ERR_OK);
            return true;
        } catch (\Exception $e) {
            exception::internal($e);
            return false;
        }
    }

    /**
     * The signature is a little different from original, because Doctrine doesn't support func_get_args() in proxies
     */
    public function parameter(string $domain, string $name, $value = '__UNINITIALIZED__')
    {
        if ($value === '__UNINITIALIZED__') {
            return $this->get_parameter($domain, $name);
        }
        return $this->set_parameter($domain, $name, $value);
    }

    public function has_attachments() : bool
    {
        return !$this->get_collection('midgard_attachment')->is_empty($this->guid);
    }

    public function list_attachments() : array
    {
        return $this->get_collection('midgard_attachment')->find($this->guid, []);
    }

    public function find_attachments(array $constraints = []) : array
    {
        return $this->get_collection('midgard_attachment')->find($this->guid, $constraints);
    }

    public function delete_attachments(array $constraints = []) : int
    {
        return $this->get_collection('midgard_attachment')->delete($this->guid, $constraints);
    }

    /**
     * @return boolean False if one or more attachments couldn't be deleted
     * @todo Implement delete_blob & return value
     */
    public function purge_attachments(array $constraints = [], bool $delete_blob = true)
    {
        return $this->get_collection('midgard_attachment')->purge($this->guid, $constraints);
    }

    public function create_attachment(string $name, string $title = '', string $mimetype = '') : ?attachment
    {
        $existing = $this->get_collection('midgard_attachment')->find($this->guid, ['name' => $name]);
        if (!empty($existing)) {
            exception::object_name_exists();
            return null;
        }
        $om = new objectmanager(connection::get_em());
        $att = $om->new_instance(connection::get_em()->getClassMetadata('midgard:midgard_attachment')->getName());

        $att->parentguid = $this->guid;
        $att->title = $title;
        $att->name = $name;
        $att->mimetype = $mimetype;
        try {
            $om->create($att);
            midgard_connection::get_instance()->set_error(MGD_ERR_OK);
            return $att;
        } catch (\Exception $e) {
            exception::internal($e);
            return null;
        }
    }

    /**
     * @todo: Tests indicate that $check_dependencies is ignored in the mgd2 extension,
     * so we might consider ignoring it, too
     */
    public function purge(bool $check_dependencies = true) : bool
    {
        if (empty($this->id)) {
            // This usually means that the object has been purged already
            exception::not_exists();
            return false;
        }
        if (   $check_dependencies
            && $this->has_dependents()) {
            exception::has_dependants();
            return false;
        }

        try {
            $om = new objectmanager(connection::get_em());
            $om->purge($this);
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            exception::not_exists();
            return false;
        } catch (\Exception $e) {
            exception::internal($e);
            return false;
        }
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);

        return true;
    }

    public static function undelete(string $guid) : bool
    {
        return \midgard_object_class::undelete($guid);
    }

    public static function new_query_builder() : \midgard_query_builder
    {
        return new \midgard_query_builder(get_called_class());
    }

    public static function new_collector(string $field, $value) : \midgard_collector
    {
        return new \midgard_collector(get_called_class(), $field, $value);
    }

    public static function new_reflection_property() : \midgard_reflection_property
    {
        return new \midgard_reflection_property(get_called_class());
    }

    public function set_guid(string $guid)
    {
        parent::__set('guid', $guid);
    }

    /**
     * Helper for managing the isapproved and islocked metadata properties
     */
    private function manage_meta_property(string $action, bool $value) : bool
    {
        if (!($this instanceof metadata_interface)) {
            exception::no_metadata();
            return false;
        }
        $user = connection::get_user();
        if ($user === null) {
            exception::access_denied();
            return false;
        }
        if ($action == 'lock') {
            $flag = 'islocked';
        } elseif ($action == 'approve') {
            $flag = 'isapproved';
        } else {
            throw new exception('Unsupported action ' . $action);
        }
        // same val
        if ($this->__get('metadata')->$flag === $value) {
            return false;
        }
        if ($value === false) {
            $action = 'un' . $action;
        }

        if ($this->id) {
            try {
                $om = new objectmanager(connection::get_em());
                $om->{$action}($this);
            } catch (\Exception $e) {
                exception::internal($e);
                return false;
            }
        }
        midgard_connection::get_instance()->set_error(MGD_ERR_OK);

        return true;
    }

    public function approve() : bool
    {
        return $this->manage_meta_property("approve", true);
    }

    public function is_approved() : bool
    {
        if (!($this instanceof metadata_interface)) {
            exception::no_metadata();
            return false;
        }
        return $this->metadata_isapproved;
    }

    public function unapprove() : bool
    {
        return $this->manage_meta_property("approve", false);
    }

    public function lock() : bool
    {
        if ($this->is_locked()) {
            exception::object_is_locked();
            return false;
        }
        return $this->manage_meta_property("lock", true);
    }

    public function is_locked() : bool
    {
        if (!($this instanceof metadata_interface)) {
            exception::no_metadata();
            return false;
        }
        return $this->metadata_islocked;
    }

    public function unlock() : bool
    {
        return $this->manage_meta_property("lock", false);
    }
}
