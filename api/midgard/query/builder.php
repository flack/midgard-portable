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
    public function add_constraint_with_property($name, $operator, $value)
    {
        try {
            return parent::add_constraint_with_property($name, $operator, $value);
        } catch (exception $e) {
            return false;
        }
    }

    public function add_constraint($name, $operator, $value)
    {
        try {
            return parent::add_constraint($name, $operator, $value);
        } catch (exception $e) {
            return false;
        }
    }

    public function execute()
    {
        $this->check_groups();
        $this->qb->addSelect('c');
        $this->pre_execution();
        $query = $this->qb->getQuery();
        $result = $query->getResult();
        $this->post_execution();
        return $result;
    }
}
