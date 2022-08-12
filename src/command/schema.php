<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace midgard\portable\command;

use midgard\portable\storage\connection;
use midgard\portable\classgenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\Question;
use midgard_storage;
use midgard_connection;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\Schema\Comparator;
use Symfony\Component\Console\Input\InputOption;
use Doctrine\Common\Proxy\ProxyGenerator;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Schema as dbal_schema;

/**
 * (Re)generate mapping information from MgdSchema XMLs
 */
class schema extends Command
{
    public $connected = false;

    protected function configure()
    {
        $this->setName('schema')
            ->setDescription('(Re)generate mapping information from MgdSchema XMLs')
            ->addArgument('config', InputArgument::OPTIONAL, 'Full path to midgard-portable config file')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore errors from DB')
            ->addOption('delete', null, InputOption::VALUE_NONE, 'Delete columns/tables that are not defined in mgdschema');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        if (!$this->connected) {
            $path = $input->getArgument('config');
            if (empty($path)) {
                if (file_exists(OPENPSA_PROJECT_BASEDIR . 'config/midgard-portable.inc.php')) {
                    $path = OPENPSA_PROJECT_BASEDIR . 'config/midgard-portable.inc.php';
                } else {
                    $dialog = $this->getHelper('question');
                    $path = $dialog->ask($input, $output, new Question('<question>Enter path to config file</question>'));
                }
            }
            if (!file_exists($path)) {
                throw new \RuntimeException('Config file ' . $path . ' not found');
            }
            //we have to delay startup so that we can delete the entity class file before it gets included
            connection::set_autostart(false);
            require $path;
        }

        $mgd_config = midgard_connection::get_instance()->config;
        $mgdschema_file = $mgd_config->vardir . '/mgdschema_classes.php';
        if (   file_exists($mgdschema_file)
            && !unlink($mgdschema_file)) {
            throw new \RuntimeException('Could not unlink ' . $mgdschema_file);
        }
        if (connection::get_parameter('dev_mode') !== true) {
            $driver = connection::get_parameter('driver');
            $classgenerator = new classgenerator($driver->get_manager(), $mgdschema_file);
            $classgenerator->write($driver->get_namespace());
        }
        if (!file_exists($mgd_config->blobdir . '/0/0')) {
            $mgd_config->create_blobdir();
        }
        connection::startup();
        $em = connection::get_em();
        connection::invalidate_cache();
        $cms = $em->getMetadataFactory()->getAllMetadata();

        // create storage
        if (    !midgard_storage::create_base_storage()
             && midgard_connection::get_instance()->get_error_string() != 'MGD_ERR_OK') {
            throw new \Exception("Failed to create base database structures" . midgard_connection::get_instance()->get_error_string());
        }
        $force = $input->getOption('force');
        $to_update = [];
        $to_create = [];

        $sm = $em->getConnection()->createSchemaManager();
        foreach ($cms as $cm) {
            if ($sm->tablesExist([$cm->getTableName()])) {
                $to_update[] = $cm;
            } else {
                $to_create[] = $cm;
            }
        }

        if (!empty($to_create)) {
            $output->writeln('Creating <info>' . count($to_create) . '</info> new tables');
            $tool = new SchemaTool($em);
            try {
                $tool->createSchema($to_create);
            } catch (\Exception $e) {
                if (!$force) {
                    throw $e;
                }
                $output->writeln('<error>' . $e->getMessage() . '</error>');
            }
        }
        if (!empty($to_update)) {
            $delete = $input->getOption('delete');
            $this->process_updates($to_update, $output, $force, $delete);
        }
        $output->writeln('Generating proxies');
        $this->generate_proxyfiles($cms);

        $output->writeln('Done');
        return 0;
    }

    private function generate_proxyfiles(array $cms)
    {
        $em = connection::get_em();
        $generator = new ProxyGenerator($em->getConfiguration()->getProxyDir(), $em->getConfiguration()->getProxyNamespace());
        $generator->setPlaceholder('baseProxyInterface', 'Doctrine\ORM\Proxy\Proxy');

        foreach ($cms as $cm) {
            $filename = $generator->getProxyFileName($cm->getName());
            if (file_exists($filename)) {
                unlink($filename);
            }
            $generator->generateProxyClass($cm, $filename);
        }
    }

    /**
     * Since we normally don't delete old columns, we have to disable DBAL's renaming
     * detection, because otherwise a new column might just reuse an outdated one (keeping the values)
     */
    public static function diff(dbal_schema $from, dbal_schema $to, bool $delete = false) : SchemaDiff
    {
        $comparator = connection::get_em()->getConnection()->createSchemaManager()->createComparator();
        $diff = $comparator->compareSchemas($from, $to);

        foreach ($diff->changedTables as $changed_table) {
            if (!empty($changed_table->renamedColumns)) {
                if (empty($changed_table->addedColumns)) {
                    $changed_table->addedColumns = [];
                }

                foreach ($changed_table->renamedColumns as $name => $column) {
                    $changed_table->addedColumns[$column->getName()] = $column;
                    $changed_table->removedColumns[$name] = new Column($name, $column->getType());
                }
                $changed_table->renamedColumns = [];
            }
            if (!$delete) {
                $changed_table->removedColumns = [];
            }
        }
        return $diff;
    }

    private function process_updates(array $to_update, OutputInterface $output, $force, $delete)
    {
        $em = connection::get_em();
        $conn = $em->getConnection();
        $tool = new SchemaTool($em);
        $from = $conn->createSchemaManager()->createSchema();
        $to = $tool->getSchemaFromMetadata($to_update);

        $diff = self::diff($from, $to, $delete);

        if ($delete) {
            $sql = $diff->toSql($conn->getDatabasePlatform());
        } else {
            $sql = $diff->toSaveSql($conn->getDatabasePlatform());
        }

        if (empty($sql)) {
            return;
        }

        $output->writeln('Executing <info>' . count($sql) . '</info> updates');
        $progress = new ProgressBar($output);
        $progress->start(count($sql));

        foreach ($sql as $sql_line) {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(' Executing <info>' . $sql_line . '</info>');
            }
            try {
                $conn->executeQuery($sql_line);
            } catch (\Exception $e) {
                if (!$force) {
                    throw $e;
                }
                $output->writeln('<error>' . $e->getMessage() . '</error>');
            }

            $progress->advance();
        }
        $progress->finish();
        $output->writeln('');
    }
}
