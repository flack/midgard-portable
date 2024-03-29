<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\mgdschema;

class translator
{
    const TYPE_NONE = 4;
    const TYPE_BOOLEAN = 20;
    const TYPE_INT = 24;
    const TYPE_UINT = 28;
    const TYPE_FLOAT = 56;
    const TYPE_STRING = 64;
    const TYPE_LONGTEXT = 196;
    const TYPE_TIMESTAMP = 139645924049440;
    const TYPE_GUID = 139645923896704;

    private static array $typemap = [
        'unsigned integer' => self::TYPE_UINT,
        'integer' => self::TYPE_INT,
        'boolean' => self::TYPE_BOOLEAN,
        'bool' => self::TYPE_BOOLEAN,
        'guid' => self::TYPE_GUID,
        //'varchar(80)' => self::TYPE_GUID, // <== true for all cases?
        'string' => self::TYPE_STRING,
        'datetime' => self::TYPE_TIMESTAMP,
        'date' => self::TYPE_TIMESTAMP,
        'text' => self::TYPE_LONGTEXT,
        'longtext' => self::TYPE_LONGTEXT,
        'float' => self::TYPE_FLOAT
    ];

    public static function to_phptype(string $typeattribute) : string
    {
        if (!isset(self::$typemap[$typeattribute])) {
            throw new \Exception('unknown type ' . $typeattribute);
        }
        $search = ['unsigned ', 'guid', 'datetime', 'longtext', 'text'];
        $replace = ['', 'string', 'midgard_datetime', 'string', 'string'];

        return str_replace($search, $replace, $typeattribute);
    }

    public static function to_constant(string $typeattribute) : int
    {
        if (!isset(self::$typemap[$typeattribute])) {
            throw new \Exception('unknown type ' . $typeattribute);
        }
        return self::$typemap[$typeattribute];
    }
}
