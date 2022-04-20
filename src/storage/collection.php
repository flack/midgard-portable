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

    public function __construct(string $classname)
    {
        $this->classname = $classname;
    }

    public function is_empty(string $guid) : bool
    {
        $qb = $this->get_qb($guid);
        return $qb->count() == 0;
    }

    public function find(string $guid, array $constraints) : array
    {
        $qb = $this->get_qb($guid);
        $this->apply_qb_constraints($qb, $constraints);
        return $qb->execute();
    }

    public function delete(string $guid, array $constraints) : int
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

    public function purge(string $guid, array $constraints) : int
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

    private function get_qb(string $guid) : midgard_query_builder
    {
        $qb = new midgard_query_builder(connection::get_fqcn($this->classname));
        $qb->add_constraint('parentguid', '=', $guid);
        return $qb;
    }

    private function apply_qb_constraints(midgard_query_builder $qb, array $constraints)
    {
        foreach ($constraints as $name => $value) {
            $qb->add_constraint($name, '=', $value);
        }
    }
}
