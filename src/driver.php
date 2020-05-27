<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\mgdschema\manager;
use midgard\portable\mgdschema\translator;
use midgard\portable\mgdschema\property;
use midgard\portable\storage\type\datetime;
use Doctrine\Persistence\Mapping\Driver\MappingDriver as driver_interface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Mapping\ClassMetadata as CM;
use Doctrine\DBAL\Types\Types;

class driver implements driver_interface
{
    private $dbtypemap = [
        'unsigned integer' => ['type' => Types::INTEGER, 'default' => 0], // <== UNSIGNED in Doctrine\DBAL\Schema\Column
        'integer' => ['type' => Types::INTEGER, 'default' => 0],
        'boolean' => ['type' => Types::BOOLEAN, 'default' => false],
        'bool' => ['type' => Types::BOOLEAN, 'default' => false],
        'guid' => ['type' => Types::STRING, 'length' => 80, 'default' => ''],
        'varchar(80)' => ['type' => Types::STRING, 'length' => 80, 'default' => ''],
        'string' => ['type' => Types::STRING, 'length' => 255, 'default' => ''],
        'datetime' => ['type' => datetime::TYPE, 'default' => '0001-01-01 00:00:00'],
        'text' => ['type' => Types::TEXT],
        'longtext' => ['type' => Types::TEXT],
        'float' => ['type' => Types::FLOAT, 'default' => 0.0],
        'double' => ['type' => Types::FLOAT, 'default' => 0.0]
    ];

    private $vardir;

    private $types;

    private $namespace;

    private $manager;

    /**
     * keep track of the namespaces already in use and
     * remember the used manager instance for resolving types
     *
     * @var array
     */
    private static $processed_namespaces = [];

    /**
     * indicates whether the current namespace has been used before
     *
     * @var boolean
     */
    private $is_fresh_namespace;

    public function __construct(array $schemadirs, string $vardir, string $namespace = 'midgard\\portable\\entities')
    {
        $this->vardir = $vardir . '/';
        $this->namespace = $namespace;

        $this->is_fresh_namespace = !isset(self::$processed_namespaces[$this->namespace]);
        if ($this->is_fresh_namespace) {
            $this->manager = new manager($schemadirs, $this->namespace);
            self::$processed_namespaces[$this->namespace] = ["manager" => $this->manager];
        } else {
            // reuse manager instance
            $this->manager = self::$processed_namespaces[$this->namespace]["manager"];
        }
    }

    public function is_fresh_namespace() : bool
    {
        return $this->is_fresh_namespace;
    }

    public function get_namespace() : string
    {
        return $this->namespace;
    }

    public function get_manager() : manager
    {
        return $this->manager;
    }

    public function get_vardir() : string
    {
        return rtrim($this->vardir, '/');
    }

    private function initialize()
    {
        $this->types = $this->manager->get_types();
    }

    /**
     * {@inheritDoc}
     */
    public function getAllClassNames() : array
    {
        if ($this->types === null) {
            $this->initialize();
        }

        return array_keys($this->types);
    }

    /**
     * {@inheritDoc}
     */
    public function isTransient($classname) : bool
    {
        if ($this->types === null) {
            $this->initialize();
        }
        return !isset($this->types[$classname]);
    }

    /**
     * {@inheritDoc}
     */
    public function loadMetadataForClass($classname, ClassMetadata $metadata)
    {
        if ($this->types === null) {
            $this->initialize();
        }
        if (!isset($this->types[$classname])) {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($classname);
        }

        $type = $this->types[$classname];

        // TODO: extends

        $table = [
            'name' => '`' . $type->table . '`',
            'options' => [
                //Doctrine's default on MySQL is InnoDB, and the foreign keys don't play well with Midgard logic
                //TODO: Maybe at some point we could try to figure out how to explicitly disable foreign key constraint creation instead
                'engine' => 'MyISAM'
            ]
        ];

        $metadata->setPrimaryTable($table);

        $metadata->midgard['parent'] = $type->parent;
        $metadata->midgard['parentfield'] = $type->parentfield;
        $metadata->midgard['upfield'] = $type->upfield;
        $metadata->midgard['childtypes'] = $this->manager->get_child_classes($type->name);
        $metadata->midgard['field_aliases'] = $type->field_aliases;

        foreach ($type->get_properties() as $name => $property) {
            // doctrine can handle id links only
            if (   $property->link
                && $target_class = $this->manager->resolve_targetclass($property)) {
                $link_mapping = [
                    'fieldName' => $property->name,
                    'targetEntity' => $target_class,
                    'joinColumns' => [
                        [
                            'name' => $property->field,
                            'referencedColumnName' => $property->link['field']
                        ]
                    ],
                    'midgard:link_target' => $property->link['field'],
                    'midgard:link_name' => $property->link['target'],
                ];

                if ($link_mapping['fieldName'] == 'id') {
                    $link_mapping['id'] = true;
                }

                $metadata->mapManyToOne($link_mapping);
                continue;
            }

            $mapping = $this->dbtypemap[$property->dbtype] ?? $this->parse_dbtype($property);

            if ($property->unique) {
                if ($property->name == 'guid') {
                    $mapping['unique'] = true;
                } else {
                    //we can't set this as a real DB constraint because of softdelete and tree hierarchies
                    $metadata->midgard['unique_fields'][] = $property->name;
                }
            }

            $mapping['columnName'] = $property->field;
            $mapping['midgard:midgard_type'] = translator::to_constant($property->type);
            $mapping['midgard:description'] = $property->description;

            // its some other link (like guid link)
            if ($property->noidlink) {
                $mapping['noidlink'] = $property->noidlink;
            }

            if ($property->name == $type->primaryfield) {
                $mapping['id'] = true;
                unset($mapping['default']);
                if ($mapping['type'] == Types::INTEGER) {
                    $metadata->setIdGeneratorType(CM::GENERATOR_TYPE_AUTO);
                } else {
                    $metadata->setIdGeneratorType(CM::GENERATOR_TYPE_NONE);
                }
            }

            $mapping['fieldName'] = $name;

            $metadata->mapField($mapping);

            if ($property->index) {
                if (empty($metadata->table['indexes'])) {
                    $metadata->table['indexes'] = [];
                }
                $metadata->table['indexes'][$type->name . '_' . $property->name . '_idx'] = ['columns' => [$property->field]];
            }
        }
    }

    private function parse_dbtype(property $property) : array
    {
        if (strpos($property->dbtype, 'varchar') === 0) {
            $mapping = [
                'type' => Types::STRING,
            ];

            if (substr($property->dbtype, -1) == ')') {
                $mapping['length'] = (int) substr($property->dbtype, 8, -1);
                return $mapping;
            }

            if (substr($property->dbtype, -8) == ') binary') {
                // see http://www.doctrine-project.org/jira/browse/DDC-1817
                $mapping['length'] = (int) substr($property->dbtype, 8, -1);
                $mapping['comment'] = 'BINARY';
                return $mapping;
            }
        } elseif (strpos($property->dbtype, 'set') === 0) {
            // see http://docs.doctrine-project.org/en/latest/cookbook/mysql-enums.html
            if (!empty($this->dbtypemap[$property->type])) {
                $mapping = $this->dbtypemap[$property->type];
                $mapping['comment'] = $property->dbtype;
                return $mapping;
            }
        } elseif (strpos(strtolower($property->dbtype), 'decimal') === 0) {
            $matches = [];
            preg_match('/DECIMAL\((\d+),(\d+)\)/i', $property->dbtype, $matches);
            $mapping = [
                'type' => Types::DECIMAL,
                'precision' => $matches[1],
                'scale' => $matches[2]
            ];
            return $mapping;
        }

        throw new \Exception($property->get_parent()->name . ': ' . $property->name . ' ' . $property->dbtype . ' not implemented yet');
    }
}
