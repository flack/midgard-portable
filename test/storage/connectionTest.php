<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test\connection;

use midgard_connection;
use midgard\portable\storage\connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use PHPUnit\Framework\TestCase;
use midgard\portable\test\testcase as mgdcase;

class connectionTest extends TestCase
{
    public function test_get_config()
    {
        $driver = mgdcase::prepare_connection('', null, uniqid(__CLASS__));
        $config = midgard_connection::get_instance()->config;
        $tmpdir = $driver->get_vardir();

        $this->assertInstanceOf('midgard_config', $config);
        $this->assertEquals($tmpdir, $config->vardir);
        $this->assertEquals($tmpdir . '/cache', $config->cachedir);
        $this->assertEquals($tmpdir . '/blobs', $config->blobdir);
        $this->assertEquals($tmpdir . '/schemas', $config->sharedir);
        $this->assertEquals($tmpdir . '/log/midgard-portable.log', $config->logfilename);
    }

    public function test_default_charset()
    {
        mgdcase::prepare_connection('', null, uniqid(__CLASS__));
        $conn = connection::get_em()->getConnection();
        $expected = $conn->getDatabasePlatform() instanceof AbstractMySQLPlatform ? 'utf8mb4' : 'utf8';
        $this->assertSame($expected, $conn->getParams()['charset']);
    }

    public function test_table_options_from_database()
    {
        mgdcase::prepare_connection('', null, uniqid(__CLASS__));
        $conn = connection::get_em()->getConnection();
        if (!$conn->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->markTestSkipped('MySQL only');
        }
        $expected = $conn->fetchAssociative(
            'SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
             FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()'
        );
        $options = $conn->createSchemaManager()->createSchemaConfig()->getDefaultTableOptions();
        $this->assertSame($expected['charset'], $options['charset']);
        $this->assertSame($expected['collation'], $options['collation']);
    }
}
