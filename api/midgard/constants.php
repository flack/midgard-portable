<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\subscriber;
use midgard\portable\schema\translator;

define('MGD_OBJECT_ACTION_NONE', subscriber::ACTION_NONE);
define('MGD_OBJECT_ACTION_DELETE', subscriber::ACTION_DELETE);
define('MGD_OBJECT_ACTION_PURGE', subscriber::ACTION_PURGE);
define('MGD_OBJECT_ACTION_CREATE', subscriber::ACTION_CREATE);
define('MGD_OBJECT_ACTION_UPDATE', subscriber::ACTION_UPDATE);

define('MGD_TYPE_NONE', translator::TYPE_NONE);
define('MGD_TYPE_STRING', translator::TYPE_STRING);
define('MGD_TYPE_INT', translator::TYPE_INT);
define('MGD_TYPE_UINT', translator::TYPE_UINT);
define('MGD_TYPE_FLOAT', translator::TYPE_FLOAT);
define('MGD_TYPE_BOOLEAN', translator::TYPE_BOOLEAN);
define('MGD_TYPE_TIMESTAMP', translator::TYPE_TIMESTAMP);
define('MGD_TYPE_LONGTEXT', translator::TYPE_LONGTEXT);
define('MGD_TYPE_GUID', translator::TYPE_GUID);
?>

MGD_ERR_OK: 0
MGD_ERR_ERROR: -1
MGD_ERR_ACCESS_DENIED: -2
MGD_ERR_NO_METADATA: -3
MGD_ERR_NOT_OBJECT: -4
MGD_ERR_NOT_EXISTS: -5
MGD_ERR_INVALID_NAME: -6
MGD_ERR_DUPLICATE: -7
MGD_ERR_HAS_DEPENDANTS: -8
MGD_ERR_RANGE: -9
MGD_ERR_NOT_CONNECTED: -10
MGD_ERR_SG_NOTFOUND: -11
MGD_ERR_INVALID_OBJECT: -12
MGD_ERR_QUOTA: -13
MGD_ERR_INTERNAL: -14
MGD_ERR_OBJECT_NAME_EXISTS: -15
MGD_ERR_OBJECT_NO_STORAGE: -16
MGD_ERR_OBJECT_NO_PARENT: -17
MGD_ERR_INVALID_PROPERTY_VALUE: -18
MGD_ERR_INVALID_PROPERTY: -19
MGD_ERR_USER_DATA: -20
MGD_ERR_OBJECT_DELETED: -21
MGD_ERR_OBJECT_PURGED: -22
MGD_ERR_OBJECT_EXPORTED: -23
MGD_ERR_OBJECT_IMPORTED: -24
MGD_ERR_MISSED_DEPENDENCE: -25
MGD_ERR_TREE_IS_CIRCULAR: -26
MGD_ERR_OBJECT_IS_LOCKED: -27