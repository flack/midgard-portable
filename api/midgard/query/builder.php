<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\connection;
use Doctrine\ORM\QueryBuilder;

class midgard_query_builder
{
    private $parameters = 0;

    private $groups = array();
    private $count_groups = 0;
    private $actual_group = 0;

    private $include_deleted = false;

    /**
     *
     * @var \Doctrine\ORM\QueryBuilder
     */
    private $qb;

	function __construct($class)
    {
        $this->qb = connection::get_em()->createQueryBuilder();
        $this->qb->from($class, 'c');
    }

    public function add_constraint($name, $operator, $value)
    {
        //we are in a group
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
        $where = 'c.' . $name . ' ' . $operator . ' ?' . $this->parameters;

        if ($this->parameters == 1)
        {
            $this->qb->where($where);
        }
        else
        {
            $this->qb->andWhere($where);
        }
        $this->qb->setParameter($this->parameters, $value);
    }

    public function add_order($name, $direction = 'ASC')
    {

    }

    public function execute()
    {
        if($this->actual_group > 0)
        {
            //debug ? throw error ? notice ?
            $this->close_all_groups();
        }
        $this->qb->select('c');
        if ($this->include_deleted)
        {
            connection::get_em()->getFilters()->disable('softdelete');
        }
        $result = $this->qb->getQuery()->getResult();
        if ($this->include_deleted)
        {
            connection::get_em()->getFilters()->enable('softdelete');
        }
        return $result;
    }

    public function count()
    {

    }

    public function include_deleted()
    {
        $this->include_deleted = true;
    }

    public function set_lang($language)
    {

    }

    public function toggle_read_only($toggle = false)
    {
    }

    public function set_limit($limit)
    {
        $this->qb->setMaxResults($limit);
    }

    function set_offset($offset)
    {

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

    private function resolve_group($count)
    {
        //get dql of child-groups
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
        //get dql of this groups contraints
        $constraint_dql = '';
        foreach ($this->groups[$count]['constraints'] as $constraint)
        {
            if (!empty($constraint_dql))
            {
                $constraint_dql .= ' ' . $this->groups[$count]['operator'] . ' ';
            }
            $this->parameters++;
            $constraint_dql .= 'c.' . $constraint['name'] . ' ' . $constraint['operator'] . ' ?' . $this->parameters;
            $this->qb->setParameter($this->parameters, $constraint['value']);
        }
        //build final dql
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

    private function close_all_groups()
    {
        while ($this->actual_group > 0)
        {
            $this->end_group();
        }
    }
}
?>
