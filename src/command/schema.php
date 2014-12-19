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
use midgard_storage;
use midgard_connection;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\Schema\Comparator;
use Symfony\Component\Console\Input\InputOption;
use Doctrine\Common\Proxy\ProxyGenerator;

/**
 * (Re)generate mapping information from MgdSchema XMLs
 */
class schema extends Command
{
    protected function configure()
    {
        $this->setName('schema')
            ->setDescription('(Re)generate mapping information from MgdSchema XMLs')
            ->addArgument('config', InputArgument::OPTIONAL, 'Full path to midgard-portable config file')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore errors from DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('config');
        if (empty($path))
        {
            if (file_exists(OPENPSA_PROJECT_BASEDIR . 'config/midgard-portable.inc.php'))
            {
                $path = OPENPSA_PROJECT_BASEDIR . 'config/midgard-portable.inc.php';
            }
            else
            {
                $dialog = $this->getHelperset()->get('dialog');
                $path = $dialog->ask($output, '<question>Enter path to config file</question>');
            }
        }
        if (!file_exists($path))
        {
            throw new \RuntimeException('Config file ' . $path . ' not found');
        }
        //we have to delay startup so that we can delete the entity class file before it gets included
        connection::set_autostart(false);
        require $path;

        $mgd_config = midgard_connection::get_instance()->config;
        $mgdobjects_file = $mgd_config->vardir . '/midgard_objects.php';
        if (   file_exists($mgdobjects_file)
            && !unlink($mgdobjects_file))
        {
            throw new \RuntimeException('Could not unlink ' . $mgdobjects_file);
        }
        if (connection::get_parameter('dev_mode') !== true)
        {
            $driver = connection::get_parameter('driver');
            $classgenerator = new classgenerator($driver->get_manager(), $mgdobjects_file);
            $classgenerator->write($driver->get_namespace());
        }
        if (!file_exists($mgd_config->blobdir . '/0/0'))
        {
            $mgd_config->create_blobdir();
        }
        connection::startup();
        $em = connection::get_em();
        $cms = $em->getMetadataFactory()->getAllMetadata();

        // create storage
        if (!midgard_storage::create_base_storage())
        {
            if ($midgard->get_error_string() != 'MGD_ERR_OK')
            {
                throw new \Exception("Failed to create base database structures" . $midgard->get_error_string());
            }
        }
        $force = $input->getOption('force');
        $to_update = array();
        $to_create = array();

        foreach ($cms as $cm)
        {
            if (!$em->getConnection()->getSchemaManager()->tablesExist(array($cm->getTableName())))
            {
                $to_create[] = $cm;
            }
            else
            {
                $to_update[] = $cm;
            }
        }

        if (!empty($to_create))
        {
            $output->writeln('Creating <info>' . count($to_create) . '</info> new tables');
            $tool = new SchemaTool($em);
            try
            {
                $tool->createSchema($to_create);
            }
            catch (\Exception $e)
            {
                if (!$force)
                {
                    throw $e;
                }
                else
                {
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                }
            }
        }
        if (!empty($to_update))
        {
            $this->process_updates($to_update, $output, $force);
        }
        $output->writeln('Generating proxies');
        $this->generate_proxyfiles($cms);

        $output->writeln('Done');
    }

    private function generate_proxyfiles(array $cms)
    {
        $em = connection::get_em();
        $generator = new ProxyGenerator($em->getConfiguration()->getProxyDir(), $em->getConfiguration()->getProxyNamespace());
        $generator->setPlaceholder('baseProxyInterface', 'Doctrine\ORM\Proxy\Proxy');

        foreach ($cms as $cm)
        {
            $filename = $generator->getProxyFileName($cm->getName());
            if (file_exists($filename))
            {
                unlink($filename);
            }
            $generator->generateProxyClass($cm, $filename);
        }
    }

    private function process_updates(array $to_update, OutputInterface $output, $force)
    {
        $em = connection::get_em();
        $conn = $em->getConnection();
        $tool = new SchemaTool($em);
        $from = $conn->getSchemaManager()->createSchema();
        $to = $tool->getSchemaFromMetadata($to_update);

        $comparator = new Comparator;
        $diff = $comparator->compare($from, $to);
        foreach ($diff->changedTables as $changed_table)
        {
            if (!empty($changed_table->removedColumns))
            {
                $changed_table->removedColumns = array();
            }
        }
        $sql = $diff->toSaveSql($conn->getDatabasePlatform());

        $output->writeln('Executing <info>' . count($sql) . '</info> updates');
        $progress = $this->getHelperset()->get('progress');
        $progress->start($output, count($sql));

        foreach ($sql as $sql_line)
        {
            if ($output->getVerbosity() == OutputInterface::VERBOSITY_VERBOSE)
            {
                $output->writeln('Executing <info>' . $sql_line . '</info>');
            }
            try
            {
                $conn->executeQuery($sql_line);
            }
            catch (\Exception $e)
            {
                if (!$force)
                {
                    throw $e;
                }
                else
                {
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                }
            }

            $progress->advance();
        }
        $progress->finish();
    }
}