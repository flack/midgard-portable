<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\storage\connection;
use Doctrine\ORM\QueryBuilder;
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

        $this->parameters++;
        $this->get_current_group()->add($this->build_where($name, $operator));

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
        if (!empty($this->groupstack))
        {
            $this->get_current_group()->add($group);
        }
        else
        {
            $this->qb->andWhere($group);
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