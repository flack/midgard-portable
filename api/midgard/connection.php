<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\connection;

class midgard_connection
{
    public $config;

    private static $instance;

    private $error_code;

    private $error_string;

    private $loglevel;

    function __construct()
    {
        //??
    }

    function __destruct()
    {
        //??
    }

    public static function get_instance()
    {
        if (self::$instance === null)
        {
            self::$instance = new static;
        }
        return self::$instance;
    }

    public function copy()
    {

    }

    public function open($name)
    {

    }

    public function reopen()
    {

    }

    public function open_config(midgard_config $config)
    {
        $this->config = $config;
    }

    public function is_connected()
    {
        return false;
    }

    public function connect($signal, $callback, $userdata = '???' )
    {

    }

    public function get_error()
    {
        return $this->error_code;
    }

    public function set_error($errorcode)
    {
        return $this->error_code = $errorcode;
    }

    public function get_error_string()
    {
        return $this->error_string;
    }

    public function get_user()
    {
        return connection::get_user();
    }

    public function set_loglevel($level, $callback = '???' )
    {
        $this->loglevel = $level;
    }

    public function get_loglevel()
    {
        return $this->loglevel;
    }

    public function list_auth_types()
    {

    }

    public function enable_workspace($toggle)
    {

    }

    public function is_enabled_workspace()
    {
        return false;
    }

    public function enable_replication($toggle)
    {

    }

    public function is_enabled_replication()
    {
        return true;
    }

    public function enable_dbus($toggle)
    {

    }

    public function is_enabled_dbus()
    {
        return false;
    }

    public function enable_quota($toggle)
    {

    }

    public function is_enabled_quota()
    {
        return false;
    }

    public function get_workspace()
    {

    }

    public function set_workspace($workspace)
    {

    }

    public function get_content_manager()
    {

    }
}
?>
