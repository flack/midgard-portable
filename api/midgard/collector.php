<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\api\error\exception;
use midgard\portable\storage\connection;

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
    private $key_property = "guid";

    private $value_properties = array();

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
        $this->key_property = $property;

        return true;
    }

    public function add_value_property($property)
    {
        if ($this->_results !== null)
        {
            return false;
        }

        try
        {
            $property_select = $this->build_property_select($property);
        }
        catch (exception $e)
        {
            return false;
        }
        if (!isset($this->value_properties[$property]))
        {
            $this->value_properties[$property] = $property_select;
        }
        return true;
    }

    protected function build_property_select($property)
    {
        $parsed = $this->parse_constraint_name($property);

        // for properties like up.name
        if (   strpos($property, ".") !== false
            && !(strpos($property, "metadata") === 0))
        {
            return $parsed['name'] . " as " . str_replace(".", "_", $property);
        }

        $cm = connection::get_em()->getClassMetadata($this->classname);
        if (array_key_exists($property, $cm->midgard['field_aliases']))
        {
            return $parsed['name'] . " as " . str_replace(".", "_", $property);
        }

        if ($cm->hasAssociation($property))
        {
            return 'IDENTITY(' . $parsed['name'] . ") as " . $property;
        }

        return $parsed['name'];
    }

    public function execute()
    {
        if ($this->_results !== null)
        {
            return false;
        }
        $this->check_groups();
        $properties = $this->value_properties;
        if (!isset($this->value_properties[$this->key_property]))
        {
            try
            {
                $properties[] = $this->build_property_select($this->key_property);
            }
            catch (exception $e)
            {
                throw new exception('Property "' . $this->key_property . '" not found in "' . $this->classname . '"', exception::INVALID_PROPERTY);
            }
        }

        $this->qb->select(implode(", ", $properties));
        $this->pre_execution();
        $results = $this->qb->getQuery()->getArrayResult();
        $this->post_execution();

        $cm = connection::get_em()->getClassMetadata($this->classname);
        // map results by current key property
        $results_map = array();
        foreach ($results as $result)
        {
            foreach ($result as $key => &$value)
            {
                // for metadata fields remove the "metadata_" prefix
                if (strpos($key, "metadata_") !== false)
                {
                    $result[str_replace("metadata_", "", $key)] = $value;
                    unset($result[$key]);
                }
                // TODO: find out why Doctrine doesn't do this on its own
                if ($cm->hasAssociation($key))
                {
                    $value = (int) $value;
                }
            }
            $key = $result[$this->key_property];
            if (!isset($this->value_properties[$this->key_property]))
            {
                unset($result[$this->key_property]);
            }

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
        if (   !$this->_has_results()
            || !isset($this->_results[$key]))
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
