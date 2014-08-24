<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace midgard\portable\command;

use midgard\portable\storage\connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use midgard_storage;
use midgard_connection;

/**
 * (Re)generate mapping information from MgdSchema XMLs
 */
class schema extends Command
{
    protected function configure()
    {
        $this->setName('schema')
            ->setDescription('(Re)generate mapping information from MgdSchema XMLs')
            ->addArgument('config', InputArgument::OPTIONAL, 'Full path to midgard-portable config file');
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

        $mgdobjects_file = midgard_connection::get_instance()->config->vardir . '/midgard_objects.php';
        if (   file_exists($mgdobjects_file)
            && !unlink($mgdobjects_file))
        {
            throw new \RuntimeException('Could not unlink ' . $mgdobjects_file);
        }

        connection::startup();
        $em = connection::get_em();
        // no idea why this has to be listed explicitly...
        $types = array('midgard_repligard');
        $cms = $em->getMetadataFactory()->getAllMetadata();
        foreach ($cms as $cm)
        {
            if ($cm->reflClass->isSubclassOf('\midgard_object'))
            {
                $types[] = $cm->name;
            }
        }

        $progress = $this->getHelperset()->get('progress');
        $progress->start($output, count($types) + 1);

        // create storage
        if (!midgard_storage::create_base_storage())
        {
            if ($midgard->get_error_string() != 'MGD_ERR_OK')
            {
                throw new \Exception("Failed to create base database structures" . $midgard->get_error_string());
            }
        }
        $progress->advance();

        foreach ($types as $type)
        {
            if (!midgard_storage::class_storage_exists($type))
            {
                midgard_storage::create_class_storage($type);
            }
            // for some reason, create misses some fields under midgard2, so we call update unconditionally
            midgard_storage::update_class_storage($type);
            $progress->advance();
        }
        $progress->finish();

        $output->writeln('Storage created');
    }
}
