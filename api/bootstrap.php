<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\subscriber;
use midgard\portable\mgdschema\translator;
use midgard\portable\api\error\exception;

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

define('MGD_ERR_OK', exception::OK);
define('MGD_ERR_ERROR', exception::ERROR);
define('MGD_ERR_ACCESS_DENIED', exception::ACCESS_DENIED);
define('MGD_ERR_NO_METADATA', exception::NO_METADATA);
define('MGD_ERR_NOT_OBJECT', exception::NOT_OBJECT);
define('MGD_ERR_NOT_EXISTS', exception::NOT_EXISTS);
define('MGD_ERR_INVALID_NAME', exception::INVALID_NAME);
define('MGD_ERR_DUPLICATE', exception::DUPLICATE);
define('MGD_ERR_HAS_DEPENDANTS', exception::HAS_DEPENDANTS);
define('MGD_ERR_RANGE', exception::RANGE);
define('MGD_ERR_NOT_CONNECTED', exception::NOT_CONNECTED);
define('MGD_ERR_SG_NOTFOUND', exception::SG_NOTFOUND);
define('MGD_ERR_INVALID_OBJECT', exception::INVALID_OBJECT);
define('MGD_ERR_QUOTA', exception::QUOTA);
define('MGD_ERR_INTERNAL', exception::INTERNAL);
define('MGD_ERR_OBJECT_NAME_EXISTS', exception::OBJECT_NAME_EXISTS);
define('MGD_ERR_OBJECT_NO_STORAGE', exception::OBJECT_NO_STORAGE);
define('MGD_ERR_OBJECT_NO_PARENT', exception::OBJECT_NO_PARENT);
define('MGD_ERR_INVALID_PROPERTY_VALUE', exception::INVALID_PROPERTY_VALUE);
define('MGD_ERR_INVALID_PROPERTY', exception::INVALID_PROPERTY);
define('MGD_ERR_USER_DATA', exception::USER_DATA);
define('MGD_ERR_OBJECT_DELETED', exception::OBJECT_DELETED);
define('MGD_ERR_OBJECT_PURGED', exception::OBJECT_PURGED);
define('MGD_ERR_OBJECT_EXPORTED', exception::OBJECT_EXPORTED);
define('MGD_ERR_OBJECT_IMPORTED', exception::OBJECT_IMPORTED);
define('MGD_ERR_MISSED_DEPENDENCE', exception::MISSED_DEPENDENCE);
define('MGD_ERR_TREE_IS_CIRCULAR', exception::TREE_IS_CIRCULAR);
define('MGD_ERR_OBJECT_IS_LOCKED', exception::OBJECT_IS_LOCKED);

// TODO: this should be moved into an autoloader function at some point
class_alias('midgard\\portable\\api\\error\\exception', 'midgard_error_exception');
class_alias('midgard\\portable\\api\\config', 'midgard_config');
class_alias('midgard\\portable\\api\\dbobject', 'midgard_dbobject');
class_alias('midgard\\portable\\api\\object', 'midgard_object');
class_alias('midgard\\portable\\api\\metadata', 'midgard_metadata');
class_alias('midgard\\portable\\api\\user', 'midgard_user');
class_alias('midgard\\portable\\api\\person', 'midgard_person');
?>