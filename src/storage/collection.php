<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage;

use midgard_query_builder;

class collection
{
    private $classname;

    public function __construct($classname)
    {
        $this->classname = $classname;
    }

    /**
     * @param string $guid
     * @return boolean
     */
    public function is_empty($guid)
    {
        $qb = $this->get_qb($guid);
        return ($qb->count() == 0);
    }

    public function find($guid, array $constraints)
    {
        $qb = $this->get_qb($guid);
        $this->apply_qb_constraints($qb, $constraints);
        return $qb->execute();
    }

    /**
     * @param string $guid
     * @param array $constraints
     * @return int
     */
    public function delete($guid, array $constraints)
    {
        $qb = $this->get_qb($guid);
        $this->apply_qb_constraints($qb, $constraints);
        $params = $qb->execute();
        $deleted_count = 0;
        foreach ($params as $param) {
            if ($param->delete()) {
                $deleted_count++;
            }
        }
        return $deleted_count;
    }

    /**
     * @param string $guid
     * @param array $constraints
     * @return number
     */
    public function purge($guid, array $constraints)
    {
        $qb = $this->get_qb($guid);
        $this->apply_qb_constraints($qb, $constraints);
        $params = $qb->execute();
        $purged_count = 0;
        foreach ($params as $param) {
            if ($param->purge()) {
                $purged_count++;
            }
        }
        return $purged_count;
    }

    /**
     *
     * @param string $guid
     * @return \midgard_query_builder
     */
    private function get_qb($guid)
    {
        $qb = new \midgard_query_builder('midgard:' . $this->classname);
        $qb->add_constraint('parentguid', '=', $guid);
        return $qb;
    }

    private function apply_qb_constraints($qb, array $constraints)
    {
        foreach ($constraints as $name => $value) {
            $qb->add_constraint($name, '=', $value);
        }
        return $qb;
    }
}
