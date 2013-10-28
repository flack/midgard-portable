<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\query;
use midgard\portable\storage\connection;
use Doctrine\DBAL\Types\BooleanType;

class midgard_collector extends midgard_query_builder
{
    /**
     * the results determined by execute
     *
     * @var array
     */
    private $_results = null;

    /**
     *
     * @var string
     */
    private $_key_property = "guid";

    private $_value_properties = array("c.guid");

    function __construct($class, $field, $value)
    {
        parent::__construct($class);
        $this->add_constraint($field, '=', $value);
    }

    public function set_key_property($property)
    {
        // after execute there is no sense in changing the key property
        if ($this->_results !== null)
        {
            return false;
        }
        $this->_key_property = $property;
        $this->add_value_property($this->_key_property);
        return true;
    }

    public function add_value_property($property)
    {
        if ($this->_results !== null)
        {
            return false;
        }

        $property = $this->build_property_select($property);
        if (!isset($this->_value_properties[$property]))
        {
            $this->_value_properties[] = $property;
        }
        return true;
    }

    protected function build_property_select($property)
    {
        $constraint_name = $this->build_constraint_name($property);

        // for properties like up.name
        if (   strpos($property, ".") !== false
            && !(strpos($property, "metadata") === 0))
        {
            return $constraint_name . " as " . str_replace(".", "_", $property);
        }

        $cm = connection::get_em()->getClassMetadata($this->classname);
        if (array_key_exists($property, $cm->midgard['field_aliases']))
        {
            return $constraint_name . " as " . str_replace(".", "_", $property);
        }

        // check for properties like up (link fields)
        // up => j{num}.id as up
        $mrp = new \midgard_reflection_property($this->classname);
        // for simple fields we need no alias at all
        if (!$mrp->is_link($property))
        {
            return $constraint_name;
        }

        $join_table = $this->add_join("c", $mrp, $property);
        return $join_table . ".id as " . $property;
    }

    public function execute()
    {
        if ($this->_results !== null)
        {
            return false;
        }
        $this->check_groups();
        $this->qb->select(implode(", ", $this->_value_properties));
        $this->pre_execution();
        $results = $this->qb->getQuery()->getArrayResult();
        $this->post_execution();

        // map results by current key property
        $results_map = array();
        foreach ($results as $result)
        {
            // for metadata fields remove the "metadata_" prefix
            foreach ($result as $key => $value)
            {
                if (strpos($key, "metadata_") !== false)
                {
                    $result[str_replace("metadata_", "", $key)] = $value;
                    unset($result[$key]);
                }
            }
            $key = $result[$this->_key_property];

            unset($result[$this->_key_property]);
            $results_map[$key] = $result;
        }

        $this->_results = $results_map;
        return true;
    }

    /**
     *
     * @param string $key
     * @return array
     */
    public function get($key)
    {
        if (!$this->_has_results() || !isset($this->_results[$key]))
        {
            return false;
        }
        return $this->_results[$key];
    }

    /**
     *
     * @param string $key
     * @param string $property
     */
    public function get_subkey($key, $property)
    {
        if (!$this->_has_results() || !isset($this->_results[$key]) || !isset($this->_results[$key][$property]))
        {
            return false;
        }
        return $this->_results[$key][$property];
    }

    /**
     * check whether we got any results to work on
     *
     * @return boolean
     */
    private function _has_results()
    {
        // execute was not called or we got an empty resultset
        return !($this->_results === null || count($this->_results) == 0);
    }

    /**
     *
     *
     * @return array
     */
    public function list_keys()
    {
        if (!$this->_has_results())
        {
            return array();
        }

        $keys = array();
        foreach ($this->_results as $key => $result)
        {
            $keys[$key] = '';
        }
        return $keys;
    }
}
