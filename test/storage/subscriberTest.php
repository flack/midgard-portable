<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test\connection;

use midgard\portable\command\schema;
use midgard\portable\storage\connection;
use midgard\portable\storage\subscriber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use midgard\portable\test\testcase as mgdcase;
use midgard_storage;
use Doctrine\DBAL\Connection as dbal_connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\SchemaTool;

class subscriberTest extends TestCase
{
    private const TABLE = 'nonportable_dbtype';

    private dbal_connection $conn;

    protected function setUp() : void
    {
        mgdcase::prepare_connection('', null, uniqid(__CLASS__));
        $this->conn = connection::get_em()->getConnection();
        $this->drop_table();
        $this->assertTrue(midgard_storage::create_class_storage(connection::get_fqcn(self::TABLE)));
    }

    protected function tearDown() : void
    {
        $this->drop_table();
    }

    private function drop_table()
    {
        $sm = $this->conn->createSchemaManager();
        if ($sm->tablesExist([self::TABLE])) {
            $sm->dropTable(self::TABLE);
        }
    }

    private function require_mysql()
    {
        if (!$this->is_mysql()) {
            $this->markTestSkipped('MySQL only');
        }
    }

    private function is_mysql() : bool
    {
        return $this->conn->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }

    private function introspect() : Table
    {
        return $this->conn->createSchemaManager()->introspectTable(self::TABLE);
    }

    private function get_column(string $name) : array
    {
        return $this->conn->fetchAssociative('SHOW FULL COLUMNS FROM ' . self::TABLE . ' LIKE ' . $this->conn->quote($name));
    }

    private function update()
    {
        $this->assertTrue(midgard_storage::update_class_storage(connection::get_fqcn(self::TABLE)));
    }

    private function insert(array $data) : int
    {
        // bypass API, so that we don't need repligard
        $this->conn->insert(self::TABLE, $data + [
            'guid' => connection::generate_guid(),
            'metadata_authors' => '',
        ]);
        return (int) $this->conn->lastInsertId();
    }

    private function get_pending_sql() : array
    {
        $em = connection::get_em();
        $cm = $em->getClassMetadata(connection::get_fqcn(self::TABLE));
        $to = (new SchemaTool($em))->getSchemaFromMetadata([$cm]);
        $diff = schema::diff($this->conn->createSchemaManager()->introspectSchema(), $to);
        return $this->conn->getDatabasePlatform()->getAlterSchemaSQL($diff);
    }

    private function assert_definitions()
    {
        $info = $this->get_column('info');
        $this->assertSame("set('auth')", $info['Type']);
        $this->assertSame('NO', $info['Null']);
        $this->assertSame('', $info['Default']);

        $password = $this->get_column('password');
        $this->assertSame('varchar(13)', $password['Type']);
        $this->assertStringEndsWith('_bin', $password['Collation']);
        $this->assertSame('NO', $password['Null']);
    }

    public function test_create()
    {
        $table = $this->introspect();
        $this->assertNotNull($table->getPrimaryKey());
        $this->assertSame(['id'], $table->getPrimaryKey()->getColumns());
        $this->assertTrue($table->getColumn('id')->getAutoincrement());
        $this->assertTrue($table->getColumn('info')->getNotnull());
        $this->assertTrue($table->getColumn('password')->getNotnull());
        $unique = array_filter($table->getIndexes(), fn($index) => $index->isUnique() && !$index->isPrimary());
        $this->assertCount(1, $unique);

        if ($this->is_mysql()) {
            $this->assertSame('MyISAM', $table->getOption('engine'));
            $this->assert_definitions();
        }
    }

    public function test_values()
    {
        $id = $this->insert(['info' => 'auth', 'password' => 'Test']);
        $row = $this->conn->fetchAssociative('SELECT info, password FROM ' . self::TABLE . ' WHERE id = ' . $id);
        $this->assertSame(['info' => 'auth', 'password' => 'Test'], $row);

        $count = 'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE password = ?';
        $this->assertEquals(1, $this->conn->fetchOne($count, ['Test']));
        $this->assertEquals(0, $this->conn->fetchOne($count, ['test']), 'Column should be case sensitive');
    }

    /**
     * If definition & introspection don't match, columns would be changed on each update
     */
    public function test_no_pending_changes()
    {
        $this->assertSame([], $this->get_pending_sql());
    }

    public function test_update_existing()
    {
        $this->require_mysql();
        $this->conn->executeStatement('ALTER TABLE ' . self::TABLE . " MODIFY info VARCHAR(255) DEFAULT '' NOT NULL, MODIFY password VARCHAR(13) NOT NULL");
        $id = $this->insert(['info' => 'auth', 'password' => 'Test']);

        $this->update();
        $this->assert_definitions();
        $this->assertSame([], $this->get_pending_sql());
        $row = $this->conn->fetchAssociative('SELECT info, password FROM ' . self::TABLE . ' WHERE id = ' . $id);
        $this->assertSame(['info' => 'auth', 'password' => 'Test'], $row);
    }

    /**
     * Values that don't fit the set must not get lost
     */
    public function test_update_existing_invalid_data()
    {
        $this->require_mysql();
        $this->conn->executeStatement('ALTER TABLE ' . self::TABLE . " MODIFY info VARCHAR(255) DEFAULT '' NOT NULL");
        $id = $this->insert(['info' => 'something else', 'password' => '']);

        try {
            $this->update();
            $this->fail('Exception expected');
        } catch (DriverException $e) {
            $this->assertStringContainsString('info', $e->getMessage());
        }
        $this->assertSame('something else', $this->conn->fetchOne('SELECT info FROM ' . self::TABLE . ' WHERE id = ' . $id));
    }

    public function test_update_changed_set()
    {
        $this->require_mysql();
        $this->conn->executeStatement('ALTER TABLE ' . self::TABLE . " MODIFY info set('auth','other') DEFAULT '' NOT NULL COMMENT 'set(''auth'')'");
        $this->update();
        $this->assert_definitions();
    }

    /**
     * The definition must be kept if the column is changed for some other reason
     */
    public function test_update_changed_length()
    {
        $this->require_mysql();
        $this->conn->executeStatement('ALTER TABLE ' . self::TABLE . " MODIFY password VARCHAR(5) BINARY NOT NULL COMMENT 'BINARY'");
        $this->update();
        $this->assert_definitions();
    }

    public function test_update_lost_collation()
    {
        $this->require_mysql();
        $this->conn->executeStatement('ALTER TABLE ' . self::TABLE . " MODIFY password VARCHAR(13) NOT NULL COMMENT 'BINARY'");
        $this->update();
        $this->assert_definitions();
    }

    /**
     * Legacy databases have the collation, but not the comment
     */
    public function test_update_legacy_binary()
    {
        $this->require_mysql();
        $this->conn->executeStatement('ALTER TABLE ' . self::TABLE . " MODIFY password VARCHAR(13) BINARY NOT NULL");
        $this->update();
        $this->assert_definitions();
        $this->assertSame([], $this->get_pending_sql());
    }

    public function test_add_column()
    {
        mgdcase::drop_column(self::TABLE, 'info');
        $this->update();
        $this->assertSame([], $this->get_pending_sql());
        if ($this->is_mysql()) {
            $this->assert_definitions();
        }
    }

    public function test_table_charset_is_used()
    {
        $this->require_mysql();
        $this->conn->executeStatement('ALTER TABLE ' . self::TABLE . ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->update();
        $this->assertSame('utf8mb4_bin', $this->get_column('password')['Collation']);
        $this->assertSame([], $this->get_pending_sql());
    }

    public static function provider_limit_index_lengths() : array
    {
        return [
            'utf8mb4 too long' => ['utf8mb4', 'MyISAM', false, null, 250],
            'utf8mb3 fits' => ['utf8mb3', 'MyISAM', false, null, null],
            'explicit length is kept' => ['utf8mb4', 'MyISAM', false, 100, 100],
            'InnoDB' => ['utf8mb4', 'InnoDB', false, null, null],
            'unique' => ['utf8mb4', 'MyISAM', true, null, null],
        ];
    }

    private static function make_index_table(string $charset, ?int $length, string $engine = 'MyISAM', bool $unique = false) : Table
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

    private static function get_index_length(Table $table) : ?int
    {
        return $table->getIndex('grp_name_idx')->getOptions()['lengths'][0] ?? null;
    }

    #[DataProvider('provider_limit_index_lengths')]
    public function test_limit_index_lengths(string $charset, string $engine, bool $unique, ?int $length, ?int $expected)
    {
        $table = self::make_index_table($charset, $length, $engine, $unique);
        subscriber::limit_index_lengths($table);
        $this->assertSame($expected, self::get_index_length($table));
    }
}
