<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\storage\connection;
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
                $a_name = $this->build_constraint_name($name);
                list ($targetclass, $fieldname) = explode('.', $a_name);
                $targetclass = 'midgard:' . array_search($targetclass, $this->join_tables);
            }

            $mapping = connection::get_em()->getClassMetadata($targetclass)->getAssociationMapping($fieldname);
            $parentfield = $name;

            if ($mapping['targetEntity'] !== get_class($this))
            {
                $cm = connection::get_em()->getClassMetadata($mapping['targetEntity']);
                $parentfield = $cm->midgard['upfield'];
            }

            $value = $this->get_child_ids($mapping['targetEntity'], $parentfield, $value);
        }

        $this->parameters++;
        $this->get_current_group()->add($this->build_where($name, $operator));

        $this->qb->setParameter($this->parameters, $value);
        return true;
    }

    public function add_order($name, $direction = 'ASC')
    {
        $name = $this->build_constraint_name($name);
        $this->qb->orderBy($name, $direction);
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
        throw new midgard_error_exception("Not implemented");
    }

    public function toggle_read_only($toggle = false)
    {
        throw new midgard_error_exception("Not implemented");
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

    public function begin_group($operator)
    {
        if ($operator === 'OR')
        {
            $this->groupstack[] = $this->qb->expr()->orX();
        }
        else
        {
            $this->groupstack[] = $this->qb->expr()->andX();
        }
    }

    public function end_group()
    {
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

    protected function build_constraint_name($name)
    {
        $current_table = 'c';

        // metadata
        $name = str_replace('metadata.', 'metadata_', $name);
        $column = $name;
        if (strpos($name, ".") !== false)
        {
            $mrp = new \midgard_reflection_property($this->classname);

            $parts = explode('.', $name);
            $column = array_pop($parts);
            foreach ($parts as $part)
            {
                if (!$mrp->is_link($part))
                {
                    throw new \Exception($part . ' is not a link');
                }
                $targetclass = $mrp->get_link_name($part);
                $current_table = $this->add_join($current_table, $mrp, $part);
                $mrp = new \midgard_reflection_property($targetclass);
            }
        }
        return $current_table . '.' . $column;
    }

    protected function build_where($name, $operator)
    {
        $name = $this->build_constraint_name($name);
        $expression = $operator . ' ?' . $this->parameters;

        if (   $operator === 'IN'
            || $operator === 'NOT IN')
        {
            $expression = $operator . '( ?' . $this->parameters . ')';
        }
        return $name . ' ' . $expression;
    }


    protected function check_groups()
    {
        while (!empty($this->groupstack))
        {
            $this->end_group();
        }
    }

    private function get_child_ids($targetclass, $fieldname, $parent_value)
    {
        $ids = array($parent_value);

        $qb = connection::get_em()->createQueryBuilder();
        $qb->from($targetclass, 'c')
            ->where('c.' . $fieldname . ' = ?0')
            ->setParameter(0, $parent_value)
            ->select("c.id");
        $this->pre_execution();
        $results = $qb->getQuery()->getScalarResult();
        $this->post_execution();

        foreach ($results as $row)
        {
            $ids = array_merge($ids, $this->get_child_ids($targetclass, $fieldname, $row['id']));
        }

        return $ids;
    }
}