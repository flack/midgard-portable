<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\mgdschema\manager;
use midgard\portable\mgdschema\translator;
use midgard\portable\mgdschema\type;
use midgard\portable\mgdschema\property;
use midgard\portable\storage\type\datetime;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver as driver_interface;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Mapping\ClassMetadata as CM;
use Doctrine\DBAL\Types\Type as dtype;

class driver implements driver_interface
{
    private $dbtypemap = array
    (
        'unsigned integer' => array('type' => dtype::INTEGER, 'default' => 0), // <== UNSIGNED in Doctrine\DBAL\Schema\Column
        'integer' => array('type' => dtype::INTEGER, 'default' => 0),
        'boolean' => array('type' => dtype::BOOLEAN, 'default' => false),
        'bool' => array('type' => dtype::BOOLEAN, 'default' => false),
        'guid' => array('type' => dtype::STRING, 'length' => 80, 'default' => ''),
        'varchar(80)' => array('type' => dtype::STRING, 'length' => 80, 'default' => ''),
        'string' => array('type' => dtype::STRING, 'length' => 255, 'default' => ''),
        'datetime' => array('type' => datetime::TYPE, 'default' => '0001-01-01 00:00:00'),
        'text' => array('type' => dtype::TEXT),
        'longtext' => array('type' => dtype::TEXT),
        'float' => array('type' => dtype::FLOAT, 'default' => 0.0),
        'double' => array('type' => dtype::FLOAT, 'default' => 0.0)
    );

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
    private static $processed_namespaces = array();

    /**
     * indicates whether the current namespace has been used before
     *
     * @var boolean
     */
    private $is_fresh_namespace;

    public function __construct(array $schemadirs, $vardir, $namespace = 'midgard\\portable\\entities')
    {
        $this->vardir = $vardir . '/';
        $this->namespace = $namespace;

        $this->is_fresh_namespace = !array_key_exists($this->namespace, self::$processed_namespaces);
        if ($this->is_fresh_namespace)
        {
            $this->manager = new manager($schemadirs, $this->namespace);
            self::$processed_namespaces[$this->namespace] = array("manager" => $this->manager);
        }
        else
        {
            // reuse manager instance
            $this->manager = self::$processed_namespaces[$this->namespace]["manager"];
        }
    }

    public function is_fresh_namespace()
    {
        return $this->is_fresh_namespace;
    }

    public function get_namespace()
    {
        return $this->namespace;
    }

    public function get_manager()
    {
        return $this->manager;
    }

    public function get_vardir()
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
    function getAllClassNames()
    {
        if ($this->types === null)
        {
            $this->initialize();
        }

        return array_keys($this->types);
    }

    /**
     * {@inheritDoc}
     */
    function isTransient($classname)
    {
        if ($this->types === null)
        {
            $this->initialize();
        }
        return !array_key_exists($classname, $this->types);
    }

    /**
     * {@inheritDoc}
     */
    public function loadMetadataForClass($classname, ClassMetadata $metadata)
    {
        if ($this->types === null)
        {
            $this->initialize();
        }
        if (!array_key_exists($classname, $this->types))
        {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($classname);
        }

        $type = $this->types[$classname];

        // TODO: extends

        $table = array
        (
            'name' => $type->table,
            'options' => array
            (
                //Doctrine's default on MySQL is InnoDB, and the foreign keys don't play well with Midgard logic
                //TODO: Maybe at some point we could try to figure out how to explicitly disable foreign key constraint creation instead
                'engine' => 'MyISAM'
            )
        );

        $metadata->setPrimaryTable($table);

        $metadata->midgard['parent'] = $type->parent;
        $metadata->midgard['parentfield'] = $type->parentfield;
        $metadata->midgard['upfield'] = $type->upfield;
        $metadata->midgard['childtypes'] = $this->manager->get_child_classes($type->name);
        $metadata->midgard['field_aliases'] = $type->field_aliases;

        foreach ($type->get_properties() as $name => $property)
        {
            // doctrine can handle id links only
            if (   $property->link
                && $target_class = $this->manager->resolve_targetclass($property))
            {
                $link_mapping = array
                (
                    'fieldName' => $property->name,
                    'targetEntity' => $target_class,
                    'joinColumns' => array
                    (
                        array
                        (
                            'name' => $property->field,
                            'referencedColumnName' => $property->link['field']
                        )
                    ),
                    'midgard:link_target' => $property->link['field'],
                    'midgard:link_name' => $property->link['target'],
                );

                if ($link_mapping['fieldName'] == 'id')
                {
                    $link_mapping['id'] = true;
                }

                $metadata->mapManyToOne($link_mapping);
                continue;
            }

            if (empty($this->dbtypemap[$property->dbtype]))
            {
                $mapping = $this->parse_dbtype($property);
            }
            else
            {
                $mapping = $this->dbtypemap[$property->dbtype];
            }

            if ($property->unique)
            {
                if ($property->name == 'guid')
                {
                    $mapping['unique'] = true;
                }
                else
                {
                    //we can't set this as a real DB constraint because of softdelete and tree hierarchies
                    $metadata->midgard['unique_fields'][] = $property->name;
                }
            }

            $mapping['columnName'] = $property->field;
            $mapping['midgard:midgard_type'] = translator::to_constant($property->type);

            // its some other link (like guid link)
            if ($property->noidlink)
            {
                $mapping['noidlink'] = $property->noidlink;
            }

            if ($property->name == $type->primaryfield)
            {
                $mapping['id'] = true;
                unset($mapping['default']);
                if ($mapping['type'] == dtype::INTEGER)
                {
                    $metadata->setIdGeneratorType(CM::GENERATOR_TYPE_AUTO);
                }
                else
                {
                    $metadata->setIdGeneratorType(CM::GENERATOR_TYPE_NONE);
                }
            }

            $mapping['fieldName'] = $name;

            $metadata->mapField($mapping);

            if ($property->index)
            {
                if (empty($metadata->table['indexes']))
                {
                    $metadata->table['indexes'] = array();
                }
                $metadata->table['indexes'][$type->name . '_' . $property->name . '_idx'] = array('columns' => array($property->field));
            }
        }
    }

    private function parse_dbtype(property $property)
    {
        if (strpos($property->dbtype, 'varchar') === 0)
        {
            $mapping = array
            (
                'type' => dtype::STRING,
            );

            if (substr($property->dbtype, -1) == ')')
            {
                $mapping['length'] = (int) substr($property->dbtype, 8, -1);
                return $mapping;
            }

            if (substr($property->dbtype, -8) == ') binary')
            {
                // see http://www.doctrine-project.org/jira/browse/DDC-1817
                $mapping['length'] = (int) substr($property->dbtype, 8, -1);
                $mapping['comment'] = 'BINARY';
                return $mapping;
            }
        }
        else if (strpos($property->dbtype, 'set') === 0)
        {
            // see http://docs.doctrine-project.org/en/latest/cookbook/mysql-enums.html
            if (!empty($this->dbtypemap[$property->type]))
            {
                $mapping = $this->dbtypemap[$property->type];
                $mapping['comment'] = $property->dbtype;
                return $mapping;
            }
        }

        throw new \Exception($property->get_parent()->name . ': ' . $property->name . ' ' . $property->dbtype . ' not implemented yet');
    }
}
