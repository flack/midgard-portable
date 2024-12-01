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
     */
    private ?array $_results = null;

    private string $key_property = "guid";

    private array $value_properties = [];

    public function __construct(string $class, ?string $field = null, $value = null)
    {
        parent::__construct($class);
        if ($field) {
            $this->add_constraint($field, '=', $value);
        }
    }

    public function set_key_property(string $property) : bool
    {
        // after execute there is no sense in changing the key property
        if ($this->_results !== null) {
            return false;
        }
        $this->key_property = $property;

        return true;
    }

    public function add_value_property(string $property) : bool
    {
        if ($this->_results !== null) {
            return false;
        }

        if (!isset($this->value_properties[$property])) {
            try {
                $this->value_properties[$property] = $this->build_property_select($property);
            } catch (exception) {
                return false;
            }
        }
        return true;
    }

    protected function build_property_select(string $property) : string
    {
        $parsed = $this->parse_constraint_name($property);

        // for properties like up.name
        if (   str_contains($property, ".")
            && !(str_starts_with($property, "metadata"))) {
            return $parsed['name'] . " as " . str_replace(".", "_", $property);
        }

        $cm = connection::get_em()->getClassMetadata($this->classname);
        if (array_key_exists($property, $cm->midgard['field_aliases'])) {
            return $parsed['name'] . " as " . str_replace(".", "_", $property);
        }

        if ($cm->hasAssociation($property)) {
            return 'IDENTITY(' . $parsed['name'] . ") as " . $property;
        }

        return $parsed['name'];
    }

    public function execute() : bool
    {
        if ($this->_results !== null) {
            return false;
        }
        $this->check_groups();
        $properties = $this->value_properties;
        if (!isset($this->value_properties[$this->key_property])) {
            try {
                $properties[] = $this->build_property_select($this->key_property);
            } catch (exception $e) {
                throw new exception('Property "' . $this->key_property . '" not found in "' . $this->classname . '"', exception::INVALID_PROPERTY, $e);
            }
        }

        $this->qb->addSelect(implode(", ", $properties));
        $this->pre_execution();
        $results = $this->qb->getQuery()->getArrayResult();
        $this->post_execution();

        $cm = connection::get_em()->getClassMetadata($this->classname);
        // map results by current key property
        $results_map = [];
        foreach ($results as $result) {
            foreach ($result as $key => &$value) {
                // for metadata fields remove the "metadata_" prefix
                if (str_contains($key, "metadata_")) {
                    $result[str_replace("metadata_", "", $key)] = $value;
                    unset($result[$key]);
                }
                // TODO: find out why Doctrine doesn't do this on its own
                if ($cm->hasAssociation($key)) {
                    $value = (int) $value;
                }
            }
            $key = $result[$this->key_property];
            if (!isset($this->value_properties[$this->key_property])) {
                unset($result[$this->key_property]);
            }

            $results_map[$key] = $result;
        }

        $this->_results = $results_map;
        return true;
    }

    /**
     * @return array
     */
    public function get(string $key)
    {
        return $this->_results[$key] ?? false;
    }

    public function get_subkey(string $key, string $property)
    {
        return $this->_results[$key][$property] ?? false;
    }

    /**
     * check whether we got any results to work on
     */
    private function _has_results() : bool
    {
        // execute was not called or we got an empty resultset
        return !empty($this->_results);
    }

    public function list_keys() : array
    {
        if (!$this->_has_results()) {
            return [];
        }
        return array_fill_keys(array_keys($this->_results), '');
    }
}
