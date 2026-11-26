<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage\manager;

use midgard\portable\storage\connection;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider\CachingCollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider\ConnectionCollationMetadataProvider;
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

    /**
     * Unless configured explicitly, new tables use the database's charset and collation,
     * instead of DBAL's default (which is derived from the connection charset)
     *
     * {@inheritDoc}
     */
    public function createSchemaConfig()
    {
        $schema_config = parent::createSchemaConfig();
        $db_config = connection::get_parameter('db_config');
        if (isset($db_config['charset']) || isset($db_config['defaultTableOptions']['charset'])) {
            return $schema_config;
        }

        $defaults = $this->_conn->fetchAssociative(
            'SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
             FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()'
        );
        if ($defaults) {
            $options = $schema_config->getDefaultTableOptions();
            $options['charset'] = $defaults['charset'];
            $options['collation'] ??= $defaults['collation'];
            $schema_config->setDefaultTableOptions($options);
        }
        return $schema_config;
    }

    /**
     * {@inheritDoc}
     */
    public function createComparator() : comparator
    {
        return new comparator(
            $this->_platform,
            new CachingCollationMetadataProvider(
                new ConnectionCollationMetadataProvider($this->_conn)
            )
        );
    }
}
