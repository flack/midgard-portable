<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api\error;

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

    public function __construct ($message = "Undefined error", $code = self::ERROR , base_exception $previous = null)
    {
        midgard_connection::get_instance()->set_error($code);
        midgard_connection::get_instance()->set_error_string($message);
    }

    public static function ok()
    {
        return new self("MGD_ERR_OK", self::OK);
    }

    public static function access_denied()
    {
        return new self("Access Denied.", self::ACCESS_DENIED);
    }

    public static function no_metadata()
    {
        return new self("Metadata class not defined.", self::NO_METADATA);
    }

    public static function not_object()
    {
        return new self("Not Midgard Object.", self::NOT_OBJECT);
    }

    public static function not_exists()
    {
        return new self("Object does not exist.", self::NOT_EXISTS);
    }

    public static function invalid_name()
    {
        return new self("Invalid characters in object's name.", self::INVALID_NAME);
    }

    public static function duplicate()
    {
        return new self("Object already exist.", self::DUPLICATE);
    }

    public static function has_dependants()
    {
        return new self("Object has dependants.", self::HAS_DEPENDANTS);
    }

    public static function range()
    {
        return new self("Date range error.", self::RANGE);
    }

    public static function not_connected()
    {
        return new self("Not connected to the Midgard database.", self::NOT_CONNECTED);
    }

    public static function sg_notfound()
    {
        return new self("Sitegroup not found.", self::SG_NOTFOUND);
    }

    public static function invalid_object()
    {
        return new self("Object not registered as Midgard Object.", self::INVALID_OBJECT);
    }

    public static function quota()
    {
        return new self("Quota limit reached.", self::QUOTA);
    }

    public static function internal()
    {
        return new self("Critical internal error.", self::INTERNAL);
    }

    public static function object_name_exists()
    {
        return new self("Object with such name exists in tree.", self::OBJECT_NAME_EXISTS);
    }

    public static function object_no_storage()
    {
        return new self("Storage table not defined for object.", self::OBJECT_NO_STORAGE);
    }

    public static function object_no_parent()
    {
        return new self("Parent object in tree not defined.", self::OBJECT_NO_PARENT);
    }

    public static function invalid_property_value()
    {
        return new self("Invalid property value.", self::INVALID_PROPERTY_VALUE);
    }

    public static function invalid_property()
    {
        return new self("Invalid property.", self::INVALID_PROPERTY);
    }

    public static function user_data()
    {
        return new self("", self::USER_DATA);
    }

    public static function object_deleted()
    {
        return new self("Object deleted.", self::OBJECT_DELETED);
    }

    public static function object_purged()
    {
        return new self("Object purged.", self::OBJECT_PURGED);
    }

    public static function object_exported()
    {
        return new self("Object already exported.", self::OBJECT_EXPORTED);
    }

    public static function object_imported()
    {
        return new self("Object already imported.", self::OBJECT_IMPORTED);
    }

    public static function missed_dependence()
    {
        return new self("Missed dependence for object.", self::MISSED_DEPENDENCE);
    }

    public static function tree_is_circular()
    {
        return new self("Circular reference found in object's tree.", self::TREE_IS_CIRCULAR);
    }

    public static function object_is_locked()
    {
        return new self("Object is locked", self::OBJECT_IS_LOCKED);
    }
}