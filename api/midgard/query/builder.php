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

    }

    function set_offset($offset)
    {

    }

    public function begin_group($operator)
    {

    }

    public function end_group()
    {

    }
}
?>
