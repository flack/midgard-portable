<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\command;

use midgard\portable\xmlreader;
use midgard\portable\typecache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class parse extends Command
{
    protected function configure()
    {
        $this->setName('parse')
            ->setDescription('Parse MgdSchema XML directory')
            ->addArgument('directory', InputArgument::REQUIRED, 'The directory to parse')
            ->addOption('cachedir', null, InputOption::VALUE_REQUIRED, 'Output directory for generated PHP classes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $xmlreader = new xmlreader;
        $directory = $this->sanitize_dirname($input->getArgument('directory'));

        $types = array();
        foreach (glob($directory. '*.xml') as $filename)
        {
            $types[$filename] = $xmlreader->parse($filename);
        }

        $cachedir = $input->getOption('cachedir');
        if ($cachedir)
        {
            $typecache = new typecache($this->sanitize_dirname($cachedir));
        }
    }

    private function sanitize_dirname($dirname)
    {
        if (substr($dirname, -1) !== '/')
        {
            $dirname .= '/';
        }
        return $dirname;
    }
}