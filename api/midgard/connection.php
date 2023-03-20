<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\connection;
use midgard\portable\api\error\exception;
use midgard\portable\api\config;
use midgard\portable\api\user;

class midgard_connection
{
    public ?config $config = null;

    private static ?self $instance = null;

    private int $error_code = exception::OK;

    private ?string $error_string = null;

    private $loglevel;

    private array $available_loglevels = ['error', 'warn', 'warning', 'info', 'message', 'debug'];

    private bool $replication_enabled = true;

    public static function get_instance() : self
    {
        return self::$instance ??= new static;
    }

    public function copy() : self
    {
        return clone self::$instance;
    }

    public function open(string $name) : bool
    {
        if ($this->config !== null) {
            $this->error_code = exception::INTERNAL;
            $this->error_string = 'MidgardConfig already associated with MidgardConnection';
            return false;
        }
        $config = new config;
        if (!$config->read_file($name, false)) {
            return false;
        }
        $this->config = $config;
        return true;
    }

    public function open_config(config $config)
    {
        $this->config = $config;
        $this->set_loglevel($config->loglevel);
    }

    public function is_connected() : bool
    {
        return is_object($this->config);
    }

    public function get_error() : int
    {
        return $this->error_code;
    }

    public function set_error(int $errorcode)
    {
        $this->error_code = $errorcode;
        $this->error_string = null;
    }

    public function get_error_string() : string
    {
        return $this->error_string ?? exception::get_error_string($this->error_code);
    }

    public function set_error_string(string $string)
    {
        $this->error_string = $string;
    }

    public function get_user() : ?user
    {
        if (!$this->is_connected()) {
            return null;
        }
        return connection::get_user();
    }

    public function set_loglevel($level, $callback = '???') : bool
    {
        if (!in_array($level, $this->available_loglevels)) {
            return false;
        }
        $this->loglevel = $level;
        return true;
    }

    public function get_loglevel()
    {
        return $this->loglevel;
    }

    public function enable_replication(bool $toggle)
    {
        $this->replication_enabled = $toggle;
    }

    public function is_enabled_replication() : bool
    {
        return $this->replication_enabled;
    }
}
