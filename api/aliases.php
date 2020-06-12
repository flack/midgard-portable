<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

use midgard\portable\storage\subscriber;
use midgard\portable\mgdschema\translator;
use midgard\portable\api\error\exception;
use midgard\portable\api\config;
use midgard\portable\api\dbobject;
use midgard\portable\api\mgdobject;
use midgard\portable\api\metadata;
use midgard\portable\api\blob;

const MGD_OBJECT_ACTION_NONE = subscriber::ACTION_NONE;
const MGD_OBJECT_ACTION_DELETE = subscriber::ACTION_DELETE;
const MGD_OBJECT_ACTION_PURGE = subscriber::ACTION_PURGE;
const MGD_OBJECT_ACTION_CREATE = subscriber::ACTION_CREATE;
const MGD_OBJECT_ACTION_UPDATE = subscriber::ACTION_UPDATE;

const MGD_TYPE_NONE = translator::TYPE_NONE;
const MGD_TYPE_STRING = translator::TYPE_STRING;
const MGD_TYPE_INT = translator::TYPE_INT;
const MGD_TYPE_UINT = translator::TYPE_UINT;
const MGD_TYPE_FLOAT = translator::TYPE_FLOAT;
const MGD_TYPE_BOOLEAN = translator::TYPE_BOOLEAN;
const MGD_TYPE_TIMESTAMP = translator::TYPE_TIMESTAMP;
const MGD_TYPE_LONGTEXT = translator::TYPE_LONGTEXT;
const MGD_TYPE_GUID = translator::TYPE_GUID;

const MGD_ERR_OK = exception::OK;
const MGD_ERR_ERROR = exception::ERROR;
const MGD_ERR_ACCESS_DENIED = exception::ACCESS_DENIED;
const MGD_ERR_NO_METADATA = exception::NO_METADATA;
const MGD_ERR_NOT_OBJECT = exception::NOT_OBJECT;
const MGD_ERR_NOT_EXISTS = exception::NOT_EXISTS;
const MGD_ERR_INVALID_NAME = exception::INVALID_NAME;
const MGD_ERR_DUPLICATE = exception::DUPLICATE;
const MGD_ERR_HAS_DEPENDANTS = exception::HAS_DEPENDANTS;
const MGD_ERR_RANGE = exception::RANGE;
const MGD_ERR_NOT_CONNECTED = exception::NOT_CONNECTED;
const MGD_ERR_SG_NOTFOUND = exception::SG_NOTFOUND;
const MGD_ERR_INVALID_OBJECT = exception::INVALID_OBJECT;
const MGD_ERR_QUOTA = exception::QUOTA;
const MGD_ERR_INTERNAL = exception::INTERNAL;
const MGD_ERR_OBJECT_NAME_EXISTS = exception::OBJECT_NAME_EXISTS;
const MGD_ERR_OBJECT_NO_STORAGE = exception::OBJECT_NO_STORAGE;
const MGD_ERR_OBJECT_NO_PARENT = exception::OBJECT_NO_PARENT;
const MGD_ERR_INVALID_PROPERTY_VALUE = exception::INVALID_PROPERTY_VALUE;
const MGD_ERR_INVALID_PROPERTY = exception::INVALID_PROPERTY;
const MGD_ERR_USER_DATA = exception::USER_DATA;
const MGD_ERR_OBJECT_DELETED = exception::OBJECT_DELETED;
const MGD_ERR_OBJECT_PURGED = exception::OBJECT_PURGED;
const MGD_ERR_OBJECT_EXPORTED = exception::OBJECT_EXPORTED;
const MGD_ERR_OBJECT_IMPORTED = exception::OBJECT_IMPORTED;
const MGD_ERR_MISSED_DEPENDENCE = exception::MISSED_DEPENDENCE;
const MGD_ERR_TREE_IS_CIRCULAR = exception::TREE_IS_CIRCULAR;
const MGD_ERR_OBJECT_IS_LOCKED = exception::OBJECT_IS_LOCKED;

// TODO: this should be moved into an autoloader function at some point
class_alias(exception::class, 'midgard_error_exception');
class_alias(config::class, 'midgard_config');
class_alias(dbobject::class, 'midgard_dbobject');
class_alias(mgdobject::class, 'midgard_object');
class_alias(metadata::class, 'midgard_metadata');
class_alias(blob::class, 'midgard_blob');
