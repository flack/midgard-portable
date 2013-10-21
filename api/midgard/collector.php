<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\query;
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
            return;
        }
        $this->_key_property = $property;
    }

    public function add_value_property($property)
    {
        // we get all properties anyway
        return true;
    }

    public function execute()
    {
        if ($this->_results !== null)
        {
            return;
        }
        $this->check_groups();
        $this->qb->select('c');
        $this->pre_execution();
        $results = $this->qb->getQuery()->getArrayResult();
        $this->post_execution();

        // map results by current key property
        $results_map = array();
        foreach ($results as $result)
        {
            $results_map[$result[$this->_key_property]] = $result;
        }

        $this->_results = $results_map;
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
        foreach ($this->_results as $result)
        {
            $keys[$result[$this->_key_property]] = '';
        }
        return $keys;
    }

}
?>
