<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\query;
use midgard\portable\api\error\exception;

class midgard_query_builder extends query
{
	function __construct($class)
    {
        parent::__construct($class);
    }

    public function add_constraint($name, $operator, $value)
    {
        try
        {
            return parent::add_constraint($name, $operator, $value);
        }
        catch (exception $e)
        {
            return false;
        }
    }

    public function execute()
    {
        $this->check_groups();
        $this->qb->select('c');
        $this->pre_execution();
        $query = $this->qb->getQuery();
        $result = $query->getResult();
        $this->post_execution();
        return $result;
    }
}
?>
