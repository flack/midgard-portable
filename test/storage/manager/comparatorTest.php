<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test\storage\manager;

use midgard\portable\storage\connection;
use midgard\portable\storage\manager\comparator;
use midgard\portable\test\testcase as mgdcase;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class comparatorTest extends TestCase
{
    private function make_comparator() : comparator
    {
        return new comparator(new MySQL80Platform, $this->createStub(CollationMetadataProvider::class));
    }

    private static function make_table(string $charset, ?int $length, string $engine = 'MyISAM', bool $unique = false) : Table
    {
        $table = new Table('grp');
        $table->addOption('engine', $engine);
        $table->addOption('charset', $charset);
        $table->addColumn('name', Types::STRING, ['length' => 255]);
        $options = $length === null ? [] : ['lengths' => [$length]];
        if ($unique) {
            $table->addUniqueIndex(['name'], 'grp_name_idx', $options);
        } else {
            $table->addIndex(['name'], 'grp_name_idx', [], $options);
        }
        return $table;
    }

    public static function provider_truncated_index() : array
    {
        return [
            'utf8mb4 table, utf8mb3 target' => ['utf8mb4', 250, 'utf8mb3', null, 'MyISAM', false, true],
            'utf8mb4 table and target' => ['utf8mb4', 250, 'utf8mb4', 250, 'MyISAM', false, true],
            'utf8mb3 fits, so prefix is intentional' => ['utf8mb3', 250, 'utf8mb3', null, 'MyISAM', false, false],
            'wrong prefix length' => ['utf8mb4', 100, 'utf8mb3', null, 'MyISAM', false, false],
            'InnoDB' => ['utf8mb4', 250, 'utf8mb3', null, 'InnoDB', false, false],
            'unique' => ['utf8mb4', 250, 'utf8mb3', null, 'MyISAM', true, false],
        ];
    }

    #[DataProvider('provider_truncated_index')]
    public function test_truncated_index(string $from_charset, int $from_length, string $to_charset, ?int $to_length, string $engine, bool $unique, bool $expect_empty)
    {
        $from = self::make_table($from_charset, $from_length, $engine, $unique);
        $to = self::make_table($to_charset, $to_length, $engine, $unique);

        $this->assertSame($expect_empty, $this->make_comparator()->compareTables($from, $to)->isEmpty());
        // the input table must not be modified
        $this->assertSame($from_length, $from->getIndex('grp_name_idx')->getOptions()['lengths'][0]);
    }

    public function test_used_by_schema_manager()
    {
        mgdcase::prepare_connection('', null, uniqid(__CLASS__));
        $conn = connection::get_em()->getConnection();
        if (!$conn->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->markTestSkipped('MySQL only');
        }
        $this->assertInstanceOf(comparator::class, $conn->createSchemaManager()->createComparator());
    }
}
