<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\classgenerator;
use midgard\portable\mgdschema\manager;
use midgard\portable\mgdschema\translator;
use midgard\portable\mgdschema\type;
use midgard\portable\mgdschema\property;
use midgard\portable\storage\type\datetime;
use SimpleXMLCElement;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver as driver_interface;
use Doctrine\ORM\Mapping\Builder\EntityListenerBuilder;
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
        'guid' => array('type' => dtype::STRING, 'length' => 80, 'default' => ''),
        'varchar(80)' => array('type' => dtype::STRING, 'length' => 80, 'default' => ''),
        'string' => array('type' => dtype::STRING, 'length' => 255, 'default' => ''),
        'datetime' => array('type' => datetime::TYPE, 'default' => '0001-01-01 00:00:00'),
        'text' => array('type' => dtype::TEXT, 'default' => ''),
        'longtext' => array('type' => dtype::TEXT, 'default' => ''),
        'float' => array('type' => dtype::FLOAT, 'default' => 0.0),
        'double' => array('type' => dtype::FLOAT, 'default' => 0.0)
    );

    private $cachedir;

    private $schemadirs;

    private $types;

    private $namespace;

    private $manager;

    public function __construct(array $schemadirs, $cachedir, $namespace = 'midgard\\portable\\entities')
    {
        $this->manager = new manager($schemadirs, $namespace);
        $this->cachedir = $cachedir . '/';
        $this->namespace = $namespace;
        $this->export_api();
    }

    private function export_api()
    {
        if (   !extension_loaded('midgard')
            && !extension_loaded('midgard2'))
        {
            require_once dirname(dirname(dirname(__DIR__))) . '/api/bootstrap.php';
        }
    }

    public function get_namespace()
    {
        return $this->namespace;
    }

    private function initialize()
    {
        $this->types = $this->manager->get_types();
        $classgenerator = new classgenerator($this->manager, $this->cachedir . 'midgard_objects.php');
        $classgenerator->write($this->namespace);

        include $this->cachedir . 'midgard_objects.php';
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
            var_dump(array_keys($this->types));
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($classname);
        }

        $type = $this->types[$classname];

        // TODO: extends

        $table = array
        (
        	'name' => $type->table
        );

        $metadata->setPrimaryTable($table);

        $metadata->midgard['parent'] = $type->parent;
        $metadata->midgard['parentfield'] = $type->parentfield;
        $metadata->midgard['upfield'] = $type->upfield;

        foreach ($type->get_properties() as $name => $property)
        {
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

            // @todo: use primaryfield?
            if ($property->name == 'id')
            {
                $mapping['id'] = true;
            }

            $mapping['fieldName'] = $name;

            if (!empty($mapping['id']))
            {
                $metadata->setIdGeneratorType(CM::GENERATOR_TYPE_AUTO);
            }

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
                echo 'BINARY detected: set collation for column to utf8_bin. manually !!!' . "\n";
                $mapping['length'] = (int) substr($property->dbtype, 8, -1);
                return $mapping;
            }
        }
        else if (strpos($property->dbtype, 'set') === 0)
        {
            // see http://docs.doctrine-project.org/en/latest/cookbook/mysql-enums.html
            echo 'SET detected: falling back to ' . $property->type . ' !!!' . "\n";
            if (!empty($this->dbtypemap[$property->type]))
            {
                return $this->dbtypemap[$property->type];
            }
        }

        throw new \Exception($property->get_parent()->name . ': ' . $property->name . ' ' . $property->dbtype . ' not implemented yet');
    }
}
