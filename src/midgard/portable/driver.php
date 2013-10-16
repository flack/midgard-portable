<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable;

use midgard\portable\xmlreader;
use midgard\portable\classgenerator;
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

    private $inherited_mapping = array();

    public function __construct(array $schemadirs, $cachedir, $namespace = '')
    {
        $this->schemadirs = $schemadirs;
        $this->cachedir = $cachedir . '/';
        $this->namespace = $namespace;
    }

    public function get_namespace()
    {
        return $this->namespace;
    }

    private function initialize()
    {
        $reader = new xmlreader;
        $this->process_file($reader, dirname(dirname(dirname(__DIR__))) . '/xml/core.xml');

        foreach ($this->schemadirs as $schemadir)
        {
            foreach (glob($schemadir . '*.xml', GLOB_NOSORT) as $filename)
            {
                $this->process_file($reader, $filename);
            }
        }

        $tablemap = array();
        foreach ($this->types as $name => $type)
        {
            if (!array_key_exists($type->table, $tablemap))
            {
                $tablemap[$type->table] = array();
            }
            $tablemap[$type->table][] = $type;
        }
        $this->types = array();
        foreach ($tablemap as $name => $types)
        {
            if (count($types) == 1)
            {
                $this->add_type($types[0]);
            }
            else
            {
                $this->create_inherited_types($types);
            }
        }
        $classgenerator = new classgenerator($this->cachedir . 'midgard_objects.php');
        $classgenerator->write($this->types, $this->inherited_mapping, $this->namespace);

        include $this->cachedir . 'midgard_objects.php';
    }

    /**
     * This sort of provides a workaround for situations where two tables use the same name
     */
    private function create_inherited_types(array $types)
    {
        $root_type = null;
        foreach ($types as $i => $type)
        {
            if ($type->extends === 'midgard_object')
            {
                $root_type = $type;
                unset($types[$i]);
                break;
            }
        }
        if (empty($root_type))
        {
            throw new \Exception('could not determine root type of inheritance group');
        }

        foreach ($types as $type)
        {
            foreach ($type->get_properties() as $property)
            {
                if (!$root_type->has_property($property->name))
                {
                    $root_type->add_property($property);
                }
            }
            $this->inherited_mapping[$type->name] = $root_type->name;
        }
        $this->add_type($root_type);
    }

    private function process_file(xmlreader $reader, $filename)
    {
        $read = $reader->parse($filename);
        foreach ($read as $type)
        {
            $this->add_type($type);
        }
    }

    private function add_type(type $type)
    {
        $classname = $type->name;
        if (!empty($this->namespace))
        {
            if ($classname === 'midgard_user')
            {
                $type->extends = 'base_user';
            }
            if ($classname === 'midgard_person')
            {
                $type->extends = 'base_person';
            }
            $classname = $this->namespace . '\\' . $classname;
        }
        $this->types[$classname] = $type;
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
        	'name' => $type->table
        );

        $metadata->setPrimaryTable($table);

        $translator = new translator;

        foreach ($type->get_properties() as $name => $property)
        {
            if ($property->link)
            {
                $target_class = $property->link['target'];
                if (   !array_key_exists($target_class, $this->types)
                    && $target_class !== $type->name)
                {
                    if (!array_key_exists($target_class, $this->inherited_mapping))
                    {
                        //TODO: This happens when loading classes individually, f.x. in unit tests. Should we care?
                        continue;
                        throw new \Exception('Link to unknown class ' . $target_class);
                    }
                    $target_class = $this->inherited_mapping[$target_class];
                }

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
                    )
                );

                if ($link_mapping['fieldName'] == 'id')
                {
                    $link_mapping['id'] = true;
                }

                $metadata->mapOneToOne($link_mapping);
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

            $mapping['unique'] = $property->unique;
            $mapping['columnName'] = $property->field;

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
