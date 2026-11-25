<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\command\schema;
use midgard\portable\driver;
use midgard\portable\storage\connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Tools\ToolsException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class schemaTest extends testcase
{
    private const VARDIR = TESTDIR . '__output/schemaTest/var';

    protected function setUp() : void
    {
        $this->reset_db(true);
    }

    protected function tearDown() : void
    {
        // the command disables this, and it would keep all following tests from connecting to their own DB
        connection::set_autostart(true);
        $this->reset_db(false);
    }

    /**
     * The update tests need a database that survives multiple connections (so in-memory won't do), and
     * they need it for themselves, because --delete drops all tables that are not part of the schema
     */
    private function get_db_config() : array
    {
        $config = ['driver' => 'pdo_sqlite'];
        if ($db = getenv('DB')) {
            $config = require TESTDIR . $db . '.inc';
        }
        if ($config['driver'] === 'pdo_sqlite') {
            return ['driver' => 'pdo_sqlite', 'path' => self::VARDIR . '/test.sqlite'];
        }
        $config['dbname'] .= '_schema';
        return $config;
    }

    private function reset_db(bool $create)
    {
        $config = $this->get_db_config();
        if (isset($config['path'])) {
            if (file_exists($config['path'])) {
                unlink($config['path']);
            }
            return;
        }
        $dbname = $config['dbname'];
        unset($config['dbname']);
        $conn = DriverManager::getConnection($config);
        $conn->executeStatement('DROP DATABASE IF EXISTS ' . $dbname);
        if ($create) {
            $conn->executeStatement('CREATE DATABASE ' . $dbname);
        }
        $conn->close();
    }

    /**
     * Runs the command against the test database
     */
    private function run_command(array $options = [], int $verbosity = OutputInterface::VERBOSITY_NORMAL) : string
    {
        connection::set_autostart(false);
        $driver = new driver([TESTDIR . '__files/membership/'], self::VARDIR, 'schemaUpdateTest');
        connection::initialize($driver, $this->get_db_config(), true);

        $command = new schema;
        $command->connected = true;
        $output = new BufferedOutput($verbosity);
        $this->assertSame(Command::SUCCESS, $command->run(new ArrayInput($options), $output));
        return $output->fetch();
    }

    private function execute_sql(string ...$statements)
    {
        foreach ($statements as $sql) {
            connection::get_em()->getConnection()->executeStatement($sql);
        }
    }

    private function get_tables() : array
    {
        return connection::get_em()->getConnection()->createSchemaManager()->listTableNames();
    }

    private function get_columns(string $table) : array
    {
        return array_keys(connection::get_em()->getConnection()->createSchemaManager()->listTableColumns($table));
    }

    /**
     * The only (non-primary) unique index our tables have is the one for guid
     */
    private function get_guid_index(string $table) : ?string
    {
        foreach (connection::get_em()->getConnection()->createSchemaManager()->listTableIndexes($table) as $index) {
            if ($index->isUnique() && !$index->isPrimary()) {
                return $index->getName();
            }
        }
        return null;
    }

    private function create_group(string $name = 'test')
    {
        $group = $this->make_object('midgard_group');
        $group->name = $name;
        $group->city = 'Berlin';
        $group->phone = 'stale';
        $this->assert_api('create', $group);
    }

    private function fetch_row(string $sql) : array
    {
        return connection::get_em()->getConnection()->fetchAssociative($sql);
    }

    public function test_run()
    {
        $command = new schema;
        $input = new ArrayInput(['config' => __DIR__ . '/__files/config.php']);
        $output = new BufferedOutput;
        $this->assertSame(Command::SUCCESS, $command->run($input, $output));

        $this->assertStringStartsWith('Creating 5 new tables', $output->fetch());
        $vardir = dirname(__DIR__) . '/__output/commandTest/var/';
        $this->assertDirectoryExists($vardir . 'blobs/0/0');
        $this->assertFileExists($vardir . 'mgdschema_classes.php');
        $this->assertTrue(class_exists(\schemaCommandTest\midgard_group::class));
    }

    public function test_missing_config()
    {
        $command = new schema;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');
        $command->run(new ArrayInput(['config' => __DIR__ . '/__files/nonexistent.php']), new BufferedOutput);
    }

    public function test_rerun_without_changes()
    {
        $this->assertStringContainsString('Creating 5 new tables', $this->run_command());

        foreach ([[], ['--delete' => true]] as $options) {
            $output = $this->run_command($options);
            $this->assertStringNotContainsString('Creating', $output);
            $this->assertStringNotContainsString('Executing', $output);
            $this->assertStringContainsString('Done', $output);
        }
    }

    public function test_add_column()
    {
        $this->run_command();
        $this->create_group();
        $this->execute_sql('ALTER TABLE grp DROP COLUMN phone');
        $this->assertNotContains('phone', $this->get_columns('grp'));

        $output = $this->run_command();
        $this->assertStringContainsString('Executing 1 updates', $output);
        $this->assertStringNotContainsString('ALTER TABLE', $output);
        $this->assertContains('phone', $this->get_columns('grp'));
        $this->assertSame(['name' => 'test', 'city' => 'Berlin'], $this->fetch_row('SELECT name, city FROM grp'));
    }

    public function test_verbose()
    {
        $this->run_command();
        $this->execute_sql('ALTER TABLE grp DROP COLUMN phone');

        $output = $this->run_command([], OutputInterface::VERBOSITY_VERBOSE);
        $this->assertMatchesRegularExpression('/Executing ALTER TABLE grp ADD (COLUMN )?phone/', $output);
    }

    public function test_obsolete_is_kept_by_default()
    {
        $this->run_command();
        $this->execute_sql(
            "ALTER TABLE grp ADD COLUMN obsolete VARCHAR(255) DEFAULT 'keep'",
            'CREATE TABLE obsolete_table (id INT)'
        );

        $this->assertStringNotContainsString('Executing', $this->run_command());
        $this->assertContains('obsolete', $this->get_columns('grp'));
        $this->assertContains('obsolete_table', $this->get_tables());
    }

    public function test_delete()
    {
        $this->run_command();
        $tables = $this->get_tables();
        $columns = $this->get_columns('grp');
        $this->create_group();
        $this->execute_sql(
            "ALTER TABLE grp ADD COLUMN obsolete VARCHAR(255) DEFAULT 'x'",
            'CREATE TABLE obsolete_table (id INT)'
        );

        $this->run_command(['--delete' => true]);
        $this->assertEqualsCanonicalizing($columns, $this->get_columns('grp'));
        $this->assertEqualsCanonicalizing($tables, $this->get_tables());
        $this->assertSame(['name' => 'test', 'city' => 'Berlin'], $this->fetch_row('SELECT name, city FROM grp'));
    }

    public function test_delete_keeps_new_tables()
    {
        $this->run_command();
        $tables = $this->get_tables();
        $this->execute_sql(
            'DROP TABLE member',
            'CREATE TABLE obsolete_table (id INT)'
        );

        $output = $this->run_command(['--delete' => true]);
        $this->assertStringContainsString('Creating 1 new tables', $output);
        $this->assertEqualsCanonicalizing($tables, $this->get_tables());
    }

    public function test_new_column_does_not_reuse_obsolete_one()
    {
        $this->run_command();
        $this->create_group();
        $this->execute_sql('ALTER TABLE grp RENAME COLUMN phone TO obsolete');

        $this->run_command();
        $this->assertContains('phone', $this->get_columns('grp'));
        $row = $this->fetch_row('SELECT phone, obsolete FROM grp');
        $this->assertSame('stale', $row['obsolete']);
        $this->assertEmpty($row['phone']);
    }

    public function test_new_column_does_not_reuse_obsolete_one_delete()
    {
        $this->run_command();
        $this->create_group();
        $this->execute_sql('ALTER TABLE grp RENAME COLUMN phone TO obsolete');

        $this->run_command(['--delete' => true]);
        $this->assertNotContains('obsolete', $this->get_columns('grp'));
        $row = $this->fetch_row('SELECT name, phone FROM grp');
        $this->assertSame('test', $row['name']);
        $this->assertEmpty($row['phone']);
    }

    /**
     * Views are not listed as tables, but they block the name
     */
    private function prepare_create_error()
    {
        $this->run_command();
        $this->execute_sql(
            'DROP TABLE grp',
            'CREATE VIEW grp AS SELECT id FROM person'
        );
    }

    /**
     * Unique index cannot be restored if there are duplicates
     */
    private function prepare_update_error()
    {
        $this->run_command();
        $index = $this->get_guid_index('grp');
        $this->assertNotNull($index);
        connection::get_em()->getConnection()->createSchemaManager()->dropIndex($index, 'grp');
        $this->create_group('test1');
        $this->create_group('test2');
        $this->execute_sql("UPDATE grp SET guid = 'duplicate'");
    }

    public function test_create_error()
    {
        $this->prepare_create_error();
        $this->expectException(ToolsException::class);
        $this->expectExceptionMessage('already exists');
        $this->run_command();
    }

    public function test_create_error_force()
    {
        $this->prepare_create_error();
        $output = $this->run_command(['--force' => true]);
        $this->assertStringContainsString('already exists', $output);
        $this->assertStringContainsString('Done', $output);
    }

    public function test_update_error()
    {
        $this->prepare_update_error();
        $this->expectException(UniqueConstraintViolationException::class);
        $this->run_command();
    }

    public function test_update_error_force()
    {
        $this->prepare_update_error();
        $this->execute_sql('ALTER TABLE member DROP COLUMN extra');

        $output = $this->run_command(['--force' => true]);
        $this->assertStringContainsString('constraint violation', $output);
        $this->assertStringContainsString('Done', $output);
        $this->assertNull($this->get_guid_index('grp'));
        // the failing statement must not keep the others from being executed
        $this->assertContains('extra', $this->get_columns('member'));
    }
}
