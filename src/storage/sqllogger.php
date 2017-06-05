<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage;

use Monolog\Logger;
use Doctrine\DBAL\Logging\SQLLogger as base_logger;

class sqllogger implements base_logger
{
    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        $context = [];
        if (!empty($params)) {
            $context['params'] = $params;
        }
        if (!empty($types)) {
            $context['types'] = $types;
        }
        $this->logger->debug($sql, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
    }
}
