<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage\manager;

use Doctrine\DBAL\Platforms\MySQL\Comparator as MySQLComparator;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Normalizes MySQL quirks before comparing tables
 */
class comparator extends MySQLComparator
{
    public function compareTables(Table $fromTable, Table $toTable) : TableDiff
    {
        return parent::compareTables($this->normalize_truncated_indexes($fromTable, $toTable), $toTable);
    }

    /**
     * If an existing index was truncated by MySQL and the target schema doesn't need a prefix
     * (e.g. because it uses a different charset than the existing table), remove the prefix from
     * the existing index. Otherwise, the index would be recreated on every run, which fails in
     * strict mode (and truncates again otherwise)
     */
    private function normalize_truncated_indexes(Table $from, Table $to) : Table
    {
        $normalized = null;
        foreach ($from->getIndexes() as $index) {
            $length = $index->getOptions()['lengths'][0] ?? null;
            if (   !empty($length)
                && $to->hasIndex($index->getName())
                && empty($to->getIndex($index->getName())->getOptions()['lengths'][0])
                && self::get_truncated_length($from, $index) === $length) {
                $normalized ??= clone $from;
                self::set_index_length($normalized, $index, null);
            }
        }
        return $normalized ?? $from;
    }

    /**
     * MyISAM keys are limited to 1000 bytes, so e.g. a non-unique index on a utf8mb4 varchar(255) is too long.
     * In strict mode, MySQL refuses to create it, otherwise it silently creates a prefix index.
     *
     * Returns the prefix length MySQL truncates the index to, or null if it fits
     */
    public static function get_truncated_length(Table $table, Index $index, ?string $default_charset = null) : ?int
    {
        $columns = $index->getColumns();
        if (   !$table->hasOption('engine')
            || strcasecmp($table->getOption('engine'), 'MyISAM') !== 0
            || $index->isPrimary()
            || $index->isUnique()
            || count($columns) !== 1
            || !$table->hasColumn($columns[0])) {
            return null;
        }
        $column = $table->getColumn($columns[0]);
        $charset = $column->getPlatformOptions()['charset']
            ?? ($table->hasOption('charset') ? $table->getOption('charset') : null)
            ?? $default_charset;
        $bytes = self::get_bytes_per_char($charset);
        if (   $bytes === null
            || !$column->getLength()
            || $column->getLength() * $bytes <= 1000) {
            return null;
        }
        return intdiv(1000, $bytes);
    }

    public static function set_index_length(Table $table, Index $index, ?int $length)
    {
        $options = $index->getOptions();
        if ($length === null) {
            unset($options['lengths']);
        } else {
            $options['lengths'] = [$length];
        }
        $table->dropIndex($index->getName());
        $table->addIndex($index->getColumns(), $index->getName(), $index->getFlags(), $options);
    }

    private static function get_bytes_per_char(?string $charset) : ?int
    {
        return match (strtolower($charset ?? '')) {
            'utf8mb4', 'utf16', 'utf16le', 'utf32' => 4,
            'utf8', 'utf8mb3' => 3,
            'ucs2' => 2,
            'latin1', 'latin2', 'ascii', 'binary' => 1,
            default => null
        };
    }
}
