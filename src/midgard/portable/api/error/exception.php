<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */
namespace midgard\portable\api\error;

class exception extends \Exception
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
}