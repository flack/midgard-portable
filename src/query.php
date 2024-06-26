<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\storage\connection;
use midgard\portable\api\error\exception;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Composite;

abstract class query
{
    protected QueryBuilder $qb;

    protected bool $include_deleted = false;

    protected int $parameters = 0;

    protected string $classname;

    protected array $groupstack = [];

    protected array $join_tables = [];

    public function __construct(string $class)
    {
        $this->classname = $class;
        $this->qb = connection::get_em()->createQueryBuilder();
        $this->qb->from($class, 'c');
    }

    abstract public function execute();

    public function get_doctrine() : QueryBuilder
    {
        return $this->qb;
    }

    public function add_constraint_with_property(string $name, string $operator, string $property)
    {
        //TODO: INTREE & IN operator functionality ?
        $parsed = $this->parse_constraint_name($name);
        $parsed_property = $this->parse_constraint_name($property);
        $constraint = $parsed['name'] . ' ' . $operator . ' ' . $parsed_property['name'];

        $this->get_current_group()->add($constraint);
    }

    public function add_constraint(string $name, string $operator, $value)
    {
        if ($operator === 'INTREE') {
            $operator = 'IN';
            $targetclass = $this->classname;
            $fieldname = $name;

            if (str_contains($name, '.')) {
                $parsed = $this->parse_constraint_name($name);
                $fieldname = $parsed['column'];
                $targetclass = $parsed['targetclass'];
            }

            $mapping = connection::get_em()->getClassMetadata($targetclass)->getAssociationMapping($fieldname);
            $parentfield = $name;

            if ($mapping['targetEntity'] !== get_class($this)) {
                $cm = connection::get_em()->getClassMetadata($mapping['targetEntity']);
                $parentfield = $cm->midgard['upfield'];
            }

            $value = (array) $value;
            $value = array_merge($value, $this->get_child_ids($mapping['targetEntity'], $parentfield, $value));
        } elseif (in_array($operator, ['IN', 'NOT IN'], true)) {
            $value = array_values($value);
        } elseif (!in_array($operator, ['=', '>', '<', '<>', '<=', '>=', 'LIKE', 'NOT LIKE'])) {
            throw new exception('Invalid operator');
        }
        $this->parameters++;
        $this->get_current_group()->add($this->build_constraint($name, $operator, $value));
        $this->qb->setParameter($this->parameters, $value);
    }

    public function add_order(string $name, string $direction = 'ASC') : bool
    {
        if (!in_array($direction, ['ASC', 'DESC'])) {
            return false;
        }
        try {
            $parsed = $this->parse_constraint_name($name);
        } catch (exception) {
            return false;
        }

        $this->qb->addOrderBy($parsed['name'], $direction);
        return true;
    }

    public function count() : int
    {
        $select = $this->qb->getDQLPart('select');
        $this->check_groups();
        $this->qb->select("count(c.id)");
        $this->pre_execution();
        $count = (int) $this->qb->getQuery()->getSingleScalarResult();

        $this->post_execution();
        if (empty($select)) {
            $this->qb->resetDQLPart('select');
        } else {
            $this->qb->add('select', $select);
        }
        return $count;
    }

    public function set_limit($limit)
    {
        $this->qb->setMaxResults($limit);
    }

    public function set_offset($offset)
    {
        $this->qb->setFirstResult($offset);
    }

    public function include_deleted()
    {
        $this->include_deleted = true;
    }

    public function begin_group(string $operator = 'OR') : bool
    {
        if ($operator === 'OR') {
            $this->groupstack[] = $this->qb->expr()->orX();
        } elseif ($operator === 'AND') {
            $this->groupstack[] = $this->qb->expr()->andX();
        } else {
            return false;
        }

        return true;
    }

    public function end_group() : bool
    {
        if (empty($this->groupstack)) {
            return false;
        }
        $group = array_pop($this->groupstack);
        if ($group->count() > 0) {
            if (empty($this->groupstack)) {
                $this->qb->andWhere($group);
            } else {
                $this->get_current_group()->add($group);
            }
        }
        return true;
    }

    public function get_current_group() : Composite
    {
        if (empty($this->groupstack)) {
            $this->begin_group('AND');
        }

        return $this->groupstack[(count($this->groupstack) - 1)];
    }

    protected function pre_execution()
    {
        if ($this->include_deleted) {
            connection::get_em()->getFilters()->disable('softdelete');
        }
    }

    protected function post_execution()
    {
        if ($this->include_deleted) {
            connection::get_em()->getFilters()->enable('softdelete');
        }
    }

    protected function add_collection_join(string $current_table, string $targetclass) : string
    {
        if (!isset($this->join_tables[$targetclass])) {
            $this->join_tables[$targetclass] = 'j' . count($this->join_tables);
            $c = $this->join_tables[$targetclass] . ".parentguid = " . $current_table . ".guid";
            $this->qb->innerJoin(connection::get_fqcn($targetclass), $this->join_tables[$targetclass], Join::WITH, $c);
        }
        return $this->join_tables[$targetclass];
    }

    protected function add_join(string $current_table, \midgard_reflection_property $mrp, string $property) : string
    {
        $targetclass = $mrp->get_link_name($property);
        if (!isset($this->join_tables[$targetclass])) {
            $this->join_tables[$targetclass] = 'j' . count($this->join_tables);

            // custom join
            if ($mrp->is_special_link($property)) {
                $c = $this->join_tables[$targetclass] . "." . $mrp->get_link_target($property) . " = " . $current_table . "." . $property;
                $this->qb->innerJoin(connection::get_fqcn($targetclass), $this->join_tables[$targetclass], Join::WITH, $c);
            } else {
                $this->qb->leftJoin($current_table . '.' . $property, $this->join_tables[$targetclass]);
            }
        }
        return $this->join_tables[$targetclass];
    }

    protected function parse_constraint_name(string $name) : array
    {
        $current_table = 'c';
        $targetclass = $this->classname;

        // metadata
        $name = str_replace('metadata.', 'metadata_', $name);
        $column = $name;
        if (str_contains($name, ".")) {
            $parts = explode('.', $name);
            $column = array_pop($parts);
            foreach ($parts as $part) {
                if (in_array($part, ['parameter', 'attachment'], true)) {
                    $targetclass = 'midgard_' . $part;
                    $current_table = $this->add_collection_join($current_table, $targetclass);
                } else {
                    $mrp = new \midgard_reflection_property($targetclass);

                    if (   !$mrp->is_link($part)
                        && !$mrp->is_special_link($part)) {
                        throw exception::invalid_property($part);
                    }
                    $targetclass = $mrp->get_link_name($part);
                    $current_table = $this->add_join($current_table, $mrp, $part);
                }
            }
            // mrp only gives us non-namespaced classnames, so we make it an alias
            $targetclass = connection::get_fqcn($targetclass);
        }

        $cm = connection::get_em()->getClassMetadata($targetclass);
        if (isset($cm->midgard['field_aliases'][$column])) {
            $column = $cm->midgard['field_aliases'][$column];
        }

        if (   !$cm->hasField($column)
            && !$cm->hasAssociation($column)) {
            throw exception::invalid_property($column);
        }

        return [
            'name' => $current_table . '.' . $column,
            'column' => $column,
            'targetclass' => $targetclass
        ];
    }

    protected function build_constraint(string $name, string $operator, $value)
    {
        $parsed = $this->parse_constraint_name($name);
        $expression = $operator . ' ?' . $this->parameters;

        if (in_array($operator, ['IN', 'NOT IN'], true)) {
            $expression = $operator . '( ?' . $this->parameters . ')';
        }

        if (   $value === 0
            || $value === null
            || is_array($value)) {
            $cm = connection::get_em()->getClassMetadata($parsed['targetclass']);
            if ($cm->hasAssociation($parsed['column'])) {
                $group = false;
                // TODO: there seems to be no way to make Doctrine accept default values for association fields,
                // so we need a silly workaround for existing DBs
                if (in_array($operator, ['<>', '>'], true)) {
                    $group = $this->qb->expr()->andX();
                    $group->add($parsed['name'] . ' IS NOT NULL');
                } elseif ($operator === 'IN') {
                    if (array_search(0, $value) !== false) {
                        $group = $this->qb->expr()->orX();
                        $group->add($parsed['name'] . ' IS NULL');
                    }
                } elseif ($operator === 'NOT IN') {
                    if (array_search(0, $value) === false) {
                        $group = $this->qb->expr()->orX();
                        $group->add($parsed['name'] . ' IS NULL');
                    }
                } else {
                    $group = $this->qb->expr()->orX();
                    $group->add($parsed['name'] . ' IS NULL');
                }
                if ($group) {
                    $group->add($parsed['name'] . ' ' . $expression);
                    return $group;
                }
            }
        }

        return $parsed['name'] . ' ' . $expression;
    }

    protected function check_groups()
    {
        while (!empty($this->groupstack)) {
            $this->end_group();
        }
    }

    private function get_child_ids(string $targetclass, string $fieldname, array $parent_values) : array
    {
        $qb = connection::get_em()->createQueryBuilder();
        $qb->from($targetclass, 'c')
            ->where('c.' . $fieldname . ' IN (?0)')
            ->setParameter(0, $parent_values)
            ->select("c.id");

        $this->pre_execution();
        $results = $qb->getQuery()->getScalarResult();
        $this->post_execution();

        $ids = array_map('current', $results);
        if (!empty($ids)) {
            $ids = array_merge($ids, $this->get_child_ids($targetclass, $fieldname, $ids));
        }

        return $ids;
    }
}
