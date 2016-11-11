<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api\error;

use midgard\portable\storage\connection;
use midgard_connection;
use Exception as base_exception;

class exception extends base_exception
{
    const OK = 0;
    const ERROR = -1;
    const ACCESS_DENIED = -2;
    const NO_METADATA = -3;
    const NOT_OBJECT = -4;
    const NOT_EXISTS = -5;
    const INVALID_NAME = -6;
    const DUPLICATE = -7;
    const HAS_DEPENDANTS = -8;
    const RANGE = -9;
    const NOT_CONNECTED = -10;
    const SG_NOTFOUND = -11;
    const INVALID_OBJECT = -12;
    const QUOTA = -13;
    const INTERNAL = -14;
    const OBJECT_NAME_EXISTS = -15;
    const OBJECT_NO_STORAGE = -16;
    const OBJECT_NO_PARENT = -17;
    const INVALID_PROPERTY_VALUE = -18;
    const INVALID_PROPERTY = -19;
    const USER_DATA = -20;
    const OBJECT_DELETED = -21;
    const OBJECT_PURGED = -22;
    const OBJECT_EXPORTED = -23;
    const OBJECT_IMPORTED = -24;
    const MISSED_DEPENDENCE = -25;
    const TREE_IS_CIRCULAR = -26;
    const OBJECT_IS_LOCKED = -27;

    private static $messages = array(
        self::OK => "MGD_ERR_OK",
        self::ACCESS_DENIED => "Access Denied.",
        self::NO_METADATA => "Metadata class not defined.",
        self::NOT_OBJECT => "Not Midgard Object.",
        self::NOT_EXISTS => "Object does not exist.",
        self::INVALID_NAME => "Invalid characters in object's name.",
        self::DUPLICATE => "Object already exist.",
        self::HAS_DEPENDANTS => "Object has dependants.",
        self::RANGE => "Date range error.",
        self::NOT_CONNECTED => "Not connected to the Midgard database.",
        self::SG_NOTFOUND => "Sitegroup not found.",
        self::INVALID_OBJECT => "Object not registered as Midgard Object.",
        self::QUOTA => "Quota limit reached.",
        self::INTERNAL => "Critical internal error. ",
        self::OBJECT_NAME_EXISTS => "Object with such name exists in tree.",
        self::OBJECT_NO_STORAGE => "Storage table not defined for object.",
        self::OBJECT_NO_PARENT => "Parent object in tree not defined.",
        self::INVALID_PROPERTY_VALUE => "Invalid property value.",
        self::INVALID_PROPERTY => "Invalid property.",
        self::USER_DATA => "",
        self::OBJECT_DELETED => "Object deleted.",
        self::OBJECT_PURGED => "Object purged.",
        self::OBJECT_EXPORTED => "Object already exported.",
        self::OBJECT_IMPORTED => "Object already imported.",
        self::MISSED_DEPENDENCE => "Missed dependence for object.",
        self::TREE_IS_CIRCULAR => "Circular reference found in object's tree.",
        self::OBJECT_IS_LOCKED => "Object is locked",
    );

    public function __construct($message = "Undefined error", $code = self::ERROR, base_exception $previous = null)
    {
        midgard_connection::get_instance()->set_error($code);
        midgard_connection::get_instance()->set_error_string($message);
        parent::__construct($message);
    }

    public static function ok()
    {
        return new self(self::$messages[self::OK], self::OK);
    }

    public static function access_denied()
    {
        return new self(self::$messages[self::ACCESS_DENIED], self::ACCESS_DENIED);
    }

    public static function no_metadata()
    {
        return new self(self::$messages[self::NO_METADATA], self::NO_METADATA);
    }

    public static function not_object()
    {
        return new self(self::$messages[self::NOT_OBJECT], self::NOT_OBJECT);
    }

    public static function not_exists()
    {
        return new self(self::$messages[self::NOT_EXISTS], self::NOT_EXISTS);
    }

    public static function invalid_name()
    {
        return new self(self::$messages[self::INVALID_NAME], self::INVALID_NAME);
    }

    public static function duplicate()
    {
        return new self(self::$messages[self::DUPLICATE], self::DUPLICATE);
    }

    public static function has_dependants()
    {
        return new self(self::$messages[self::HAS_DEPENDANTS], self::HAS_DEPENDANTS);
    }

    public static function range()
    {
        return new self(self::$messages[self::RANGE], self::RANGE);
    }

    public static function not_connected()
    {
        return new self(self::$messages[self::NOT_CONNECTED], self::NOT_CONNECTED);
    }

    public static function sg_notfound()
    {
        return new self(self::$messages[self::SG_NOTFOUND], self::SG_NOTFOUND);
    }

    public static function invalid_object()
    {
        return new self(self::$messages[self::INVALID_OBJECT], self::INVALID_OBJECT);
    }

    public static function quota()
    {
        return new self(self::$messages[self::QUOTA], self::QUOTA);
    }

    public static function internal(base_exception $exception)
    {
        $message = self::$messages[self::INTERNAL];
        connection::log()->critical($message, array('exception' => $exception));
        return new self($message . '. ' . $exception->getMessage(), self::INTERNAL, $exception);
    }

    public static function object_name_exists()
    {
        return new self(self::$messages[self::OBJECT_NAME_EXISTS], self::OBJECT_NAME_EXISTS);
    }

    public static function object_no_storage()
    {
        return new self(self::$messages[self::OBJECT_NO_STORAGE], self::OBJECT_NO_STORAGE);
    }

    public static function object_no_parent()
    {
        return new self(self::$messages[self::OBJECT_NO_PARENT], self::OBJECT_NO_PARENT);
    }

    public static function invalid_property_value($message = null)
    {
        if ($message == null) {
            $message = self::$messages[self::INVALID_PROPERTY_VALUE];
        }
        return new self($message, self::INVALID_PROPERTY_VALUE);
    }

    public static function invalid_property()
    {
        return new self(self::$messages[self::INVALID_PROPERTY], self::INVALID_PROPERTY);
    }

    public static function user_data($message = 'Unknown error')
    {
        return new self($message, self::USER_DATA);
    }

    public static function object_deleted()
    {
        return new self(self::$messages[self::OBJECT_DELETED], self::OBJECT_DELETED);
    }

    public static function object_purged()
    {
        return new self(self::$messages[self::OBJECT_PURGED], self::OBJECT_PURGED);
    }

    public static function object_exported()
    {
        return new self(self::$messages[self::OBJECT_EXPORTED], self::OBJECT_EXPORTED);
    }

    public static function object_imported()
    {
        return new self(self::$messages[self::OBJECT_IMPORTED], self::OBJECT_IMPORTED);
    }

    public static function missed_dependence()
    {
        return new self(self::$messages[self::MISSED_DEPENDENCE], self::MISSED_DEPENDENCE);
    }

    public static function tree_is_circular()
    {
        return new self(self::$messages[self::TREE_IS_CIRCULAR], self::TREE_IS_CIRCULAR);
    }

    public static function object_is_locked()
    {
        return new self(self::$messages[self::OBJECT_IS_LOCKED], self::OBJECT_IS_LOCKED);
    }

    public static function get_error_string($code)
    {
        if (!array_key_exists($code, self::$messages)) {
            return "Undefined error";
        }
        return self::$messages[$code];
    }
}
