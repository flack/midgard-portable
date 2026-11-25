<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage\manager;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

/**
 * This class contains workarounds for reading existing Midgard databases
 */
class mysql extends MySQLSchemaManager
{
    /**
     * ENUM/SET fields are converted to string for now (Like in the XML reader)
     *
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $column = array_change_key_case($tableColumn, CASE_LOWER);

        if (in_array(strtok($column['type'], '()'), ['enum', 'set'])) {
            return new Column($column['field'], Type::getType(Types::STRING), [
                'length' => 255,
                'default' => $column['default'] ?? null,
                'notnull' => $column['null'] != 'YES',
                'comment' => $column['type']
            ]);
        }

        return parent::_getPortableTableColumnDefinition($tableColumn);
    }
}
