<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\storage\connection;
use Doctrine\ORM\QueryBuilder;

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
     * @var array
     */
    protected $groups = array();

    /**
     *
     * @var int
     */
    protected $count_groups = 0;

    /**
     *
     * @var int
     */
    protected $actual_group = 0;

    /**
     *
     * @var string
     */
    protected $classname = null;

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
            $mapping = connection::get_em()->getClassMetadata($this->classname)->getAssociationMapping($name);
            $targetclass = $mapping['midgard:link_name'];
            $value = $this->get_child_ids('midgard:' . $targetclass, $name, $value);
        }
        // we are in a group
        if ($this->count_groups > 0)
        {
            $this->groups[$this->count_groups]['constraints'][] = array(
                'name' => $name,
                'operator' => $operator,
                'value' => $value
            );
            return true;
        }
        $this->parameters++;
        $where = $this->build_where($name, $operator);

        if ($this->parameters == 1)
        {
            $this->qb->where($where);
        }
        else
        {
            $this->qb->andWhere($where);
        }
        $this->qb->setParameter($this->parameters, $value);
        return true;
    }

    public function add_order($name, $direction = 'ASC')
    {
        $name = $this->build_constraint_name($name);
        $this->qb->orderBy('c.' . $name, $direction);
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
        //set start group
        if ($this->count_groups === 0)
        {
            $this->groups[$this->count_groups] = array(
                'parent' => $this->count_groups,
                'operator' => 'AND',
                'childs' => array(),
                'constraints' => array()
            );
        }

        $parent = $this->actual_group;
        $this->count_groups++;
        //stat this group is child
        $this->groups[$parent]['childs'][] = $this->count_groups;
        //add this group
        $this->groups[$this->count_groups] = array(
            'parent' => $parent,
            'operator' => $operator,
            'childs' => array(),
            'constraints' => array()
        );

        $this->actual_group = $this->count_groups;
    }

    public function end_group()
    {
        //no group to end.... error ? notice ?
        if ($this->actual_group < 1)
        {
            return true;
        }
        //get actual_group parent
        $parent = $this->groups[$this->actual_group]['parent'];
        //if all groups were ended get dql-statement
        if ($parent === 0 && !empty($this->groups))
        {
            $dql = $this->resolve_group($this->actual_group);
            //add the dql
            $this->qb->andWhere($dql);
            //clean groups
            $this->groups = array();
        }
        $this->actual_group = $parent;
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

    protected function build_constraint_name($name)
    {
        return str_replace(".", "_", $name);
    }

    protected function build_where($name, $operator)
    {
        // metadata
        $name = str_replace('metadata.', 'metadata_', $name);

        $expression = $operator . ' ?' . $this->parameters;

        if (   $operator === 'IN'
            || $operator === 'NOT IN')
        {
            $expression = $operator . '( ?' . $this->parameters . ')';
        }
        if (strpos($name, ".") !== false)
        {
            // TODO
            $name = 'c.' . $this->build_constraint_name($name);
        }
        return 'c.' . $name . ' ' . $expression;

        /*
        $property = array_shift($parts); // eg lang

        //$class = connection::get_em()->getMetadataFactory()->getMetadataFor($this->classname);
        //var_dump($class->getReflectionProperty($property));

        // eg person.metadata.deleted
        $this->qb->addSelect(array('j'))->leftJoin('c.' . $property, 'j');
        $name = "j." . $this->build_constraint_name(implode(".", $parts));

        return $name . ' ' .$operator . ' ?' . $this->parameters;
        */
    }


    protected function check_groups()
    {
        if($this->actual_group > 0)
        {
            //debug ? throw error ? notice ?
            $this->close_all_groups();
        }
    }

    protected function resolve_group($count)
    {
        // get dql of child-groups
        $child_dql = '';
        if (!empty($this->groups[$count]['childs']))
        {
            foreach ($this->groups[$count]['childs'] as $child_count)
            {
                if (!empty($child_dql))
                {
                    $child_dql .= ' ' . $this->groups[$count]['operator'] . ' ';
                }
                $child_dql .= $this->resolve_group($child_count);
            }
        }
        // get dql of this groups contraints
        $constraint_dql = '';
        foreach ($this->groups[$count]['constraints'] as $constraint)
        {
            if (!empty($constraint_dql))
            {
                $constraint_dql .= ' ' . $this->groups[$count]['operator'] . ' ';
            }
            $this->parameters++;
            //$constraint_dql .= 'c.' . $constraint['name'] . ' ' . $constraint['operator'] . ' ?' . $this->parameters;
            $constraint_dql .= $this->build_where($constraint['name'], $constraint['operator']);

            $this->qb->setParameter($this->parameters, $constraint['value']);
        }
        // build final dql
        $dql = '';
        if ($constraint_dql !== '')
        {
            $dql .= $constraint_dql;
            if (!empty($child_dql))
            {
                $dql .= ' ' . $this->groups[$count]['operator'] . ' ';
            }
        }
        if ($child_dql !== '')
        {
            $dql .=  $child_dql;
        }
        if ($dql !== '')
        {
            $dql = '(' . $dql . ')';
        }

        return $dql;
    }

    protected function close_all_groups()
    {
        while ($this->actual_group > 0)
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