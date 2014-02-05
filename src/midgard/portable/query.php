<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\storage\connection;
use midgard\portable\api\error\exception;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Expr;

abstract class query
{
    /**
     *
     * @var \Doctrine\ORM\QueryBuilder
     */
    protected $qb;

    /**
     *
     * @var boolean
     */
    protected $include_deleted = false;

    /**
     *
     * @var int
     */
    protected $parameters = 0;

    /**
     *
     * @var string
     */
    protected $classname = null;

    /**
     *
     * @var array
     */
    protected $groupstack = array();

    /**
     *
     * @var array
     */
    protected $join_tables = array();

    public function __construct($class)
    {
        $this->classname = $class;
        $this->qb = connection::get_em()->createQueryBuilder();
        $this->qb->from($class, 'c');
    }

    abstract function execute();

    public function add_constraint($name, $operator, $value)
    {
        if ($operator === 'INTREE')
        {
            $operator = 'IN';
            $targetclass = $this->classname;
            $fieldname = $name;

            if (strpos($name, '.') !== false)
            {
                $parsed = $this->parse_constraint_name($name);
                $fieldname = $parsed['column'];
                $targetclass = $parsed['targetclass'];
            }

            $mapping = connection::get_em()->getClassMetadata($targetclass)->getAssociationMapping($fieldname);
            $parentfield = $name;

            if ($mapping['targetEntity'] !== get_class($this))
            {
                $cm = connection::get_em()->getClassMetadata($mapping['targetEntity']);
                $parentfield = $cm->midgard['upfield'];
            }

            $value = (array) $value;
            $value = array_merge($value, $this->get_child_ids($mapping['targetEntity'], $parentfield, $value));
        }
        else if (   $operator === 'IN'
                 || $operator === 'NOT IN')
        {
            $value = array_values($value);
        }

        $this->parameters++;
        $this->get_current_group()->add($this->build_constraint($name, $operator, $value));
        $this->qb->setParameter($this->parameters, $value);

        return true;
    }

    public function add_order($name, $direction = 'ASC')
    {
        $parsed = $this->parse_constraint_name($name);
        $this->qb->orderBy($parsed['name'], $direction);
        return true;
    }

    public function count()
    {
        $this->check_groups();
        $this->qb->select("count(c.id)");
        $this->pre_execution();
        $count = intval($this->qb->getQuery()->getSingleScalarResult());

        $this->post_execution();
        return $count;
    }

    public function set_lang($language)
    {
        throw new \Exception("Not implemented");
    }

    public function toggle_read_only($toggle = false)
    {
        throw \Exception("Not implemented");
    }

    public function set_limit($limit)
    {
        $this->qb->setMaxResults($limit);
    }

    function set_offset($offset)
    {
        $this->qb->setFirstResult($offset);
    }

    public function include_deleted()
    {
        $this->include_deleted = true;
    }

    public function begin_group($operator = 'OR')
    {
        if ($operator === 'OR')
        {
            $this->groupstack[] = $this->qb->expr()->orX();
        }
        else if ($operator === 'AND')
        {
            $this->groupstack[] = $this->qb->expr()->andX();
        }
        else
        {
            return false;
        }

        return true;
    }

    public function end_group()
    {
        if (empty($this->groupstack))
        {
            return false;
        }
        $group = array_pop($this->groupstack);
        if ($group->count() > 0)
        {
            if (!empty($this->groupstack))
            {
                $this->get_current_group()->add($group);
            }
            else
            {
                $this->qb->andWhere($group);
            }
        }
        return true;
    }

    /**
     *
     * @return Doctrine\ORM\Query\Expr:
     */
    protected function get_current_group()
    {
        if (empty($this->groupstack))
        {
            $this->begin_group('AND');
        }

        return $this->groupstack[(count($this->groupstack) - 1)];
    }

    protected function pre_execution()
    {
        if ($this->include_deleted)
        {
            connection::get_em()->getFilters()->disable('softdelete');
        }
    }

    protected function post_execution()
    {
        if ($this->include_deleted)
        {
            connection::get_em()->getFilters()->enable('softdelete');
        }
    }

    protected function add_join($current_table, $mrp, $property)
    {
        $targetclass = $mrp->get_link_name($property);
        if (!array_key_exists($targetclass, $this->join_tables))
        {
            $this->join_tables[$targetclass] = 'j' . count($this->join_tables);

            // custom join
            if ($mrp->is_special_link($property))
            {
                $c = $this->join_tables[$targetclass] . "." . $mrp->get_link_target($property) . " = " . $current_table . "." . $property;
                $this->qb->innerJoin("midgard:" . $targetclass, $this->join_tables[$targetclass], Join::WITH, $c);
            }
            else
            {
                $this->qb->join($current_table . '.' . $property, $this->join_tables[$targetclass]);
            }
        }
        return $this->join_tables[$targetclass];
    }

    protected function parse_constraint_name($name)
    {
        $current_table = 'c';
        $targetclass = $this->classname;

        // metadata
        $name = str_replace('metadata.', 'metadata_', $name);
        $column = $name;
        if (strpos($name, ".") !== false)
        {
            $parts = explode('.', $name);
            $column = array_pop($parts);
            foreach ($parts as $part)
            {
                $mrp = new \midgard_reflection_property($targetclass);

                if (   !$mrp->is_link($part)
                    && !$mrp->is_special_link($part))
                {
                    throw exception::ok();
                }
                $targetclass = $mrp->get_link_name($part);
                $current_table = $this->add_join($current_table, $mrp, $part);
            }
            // mrp only gives us non-namespaced classnames (TODO: verify), so we make it an alias
            $targetclass = 'midgard:' . $targetclass;
        }

        $cm = connection::get_em()->getClassMetadata($targetclass);
        if (array_key_exists($column, $cm->midgard['field_aliases']))
        {
            $column = $cm->midgard['field_aliases'][$column];
        }

        if (   !$cm->hasField($column)
            && !$cm->hasAssociation($column))
        {
            throw exception::ok();
        }

        return array
        (
            'name' => $current_table . '.' . $column,
            'column' => $column,
            'targetclass' => $targetclass
        );
    }

    protected function build_constraint($name, $operator, $value)
    {
        $parsed = $this->parse_constraint_name($name);
        $expression = $operator . ' ?' . $this->parameters;

        if (   $operator === 'IN'
            || $operator === 'NOT IN')
        {
            $expression = $operator . '( ?' . $this->parameters . ')';
        }

        if (   $value === 0
            || $value === null
            || is_array($value))
        {
            $cm = connection::get_em()->getClassMetadata($parsed['targetclass']);
            if ($cm->hasAssociation($parsed['column']))
            {
                $group = false;
                // TODO: there seems to be no way to make Doctrine accept default values for association fields,
                // so we need a silly workaorund for existing DBs
                if ($operator === '<>' || $operator === '>')
                {
                    $group = $this->qb->expr()->andX();
                    $group->add($parsed['name'] . ' IS NOT NULL');
                }
                else if ($operator === 'IN')
                {
                    if (array_search(0, $value) !== false)
                    {
                        $group = $this->qb->expr()->orX();
                        $group->add($parsed['name'] . ' IS NULL');
                    }
                }
                else if ($operator === 'NOT IN')
                {
                    if (array_search(0, $value) === false)
                    {
                        $group = $this->qb->expr()->orX();
                        $group->add($parsed['name'] . ' IS NULL');
                    }
                }
                else
                {
                    $group = $this->qb->expr()->orX();
                    $group->add($parsed['name'] . ' IS NULL');
                }
                if ($group)
                {
                    $group->add($parsed['name'] . ' ' . $expression);
                    return $group;
                }
            }
        }

        return $parsed['name'] . ' ' . $expression;
    }

    protected function check_groups()
    {
        while (!empty($this->groupstack))
        {
            $this->end_group();
        }
    }

    private function get_child_ids($targetclass, $fieldname, array $parent_values)
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
        if (!empty($ids))
        {
            $ids = array_merge($ids, $this->get_child_ids($targetclass, $fieldname, $ids));
        }

        return $ids;
    }
}