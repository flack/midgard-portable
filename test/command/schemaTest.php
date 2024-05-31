<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\test;

use midgard\portable\command\schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Command\Command;

class schemaTest extends testcase
{
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
}