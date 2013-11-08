<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage;

use midgard\portable\storage\conection;
use midgard\portable\storage\metadata\entity;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Events as dbal_events;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use midgard_repligard;

class subscriber implements EventSubscriber
{
    const ACTION_NONE = 0;
    const ACTION_DELETE = 1;
    const ACTION_PURGE = 2;
    const ACTION_CREATE = 3;
    const ACTION_UPDATE = 4;

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();

        $repligard_class = $em->getClassMetadata('midgard:midgard_repligard')->getName();
        if (!($entity instanceof $repligard_class))
        {
            if (empty($entity->guid))
            {
                $entity->set_guid(connection::generate_guid());
            }
            $ref = $em->getClassMetadata(get_class($entity))->getReflectionClass();
            $repligard_entry = new $repligard_class;
            $repligard_entry->guid = $entity->guid;
            $repligard_entry->typename = $ref->getShortName();
            $repligard_entry->object_action = self::ACTION_CREATE;
            $em->persist($repligard_entry);
        }

        if ($entity instanceof entity)
        {
            $entity->metadata->created = new \midgard_datetime();
            $entity->metadata->revised = new \midgard_datetime();
            $user = connection::get_user();
            if ($user !== null)
            {
                $entity->metadata_creator = $user->person;
                $entity->metadata_revisor = $user->person;
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();

        $repligard_class = $em->getClassMetadata('midgard:midgard_repligard')->getName();
        if (!($entity instanceof $repligard_class))
        {
            $repligard_entry = $em->getRepository('midgard:midgard_repligard')->findOneBy(array('guid' => $entity->guid));

            if (   $entity instanceof entity
                && $entity->metadata->deleted)
            {
                $repligard_entry->object_action = self::ACTION_DELETE;
            }
            else
            {
                $repligard_entry->object_action = self::ACTION_UPDATE;
            }
            $em->persist($repligard_entry);
        }

        if ($entity instanceof entity)
        {
            $entity->metadata->revised = new \midgard_datetime();
            $entity->metadata_revision++;
            $user = connection::get_user();
            if ($user !== null)
            {
                $entity->metadata_revisor = $user->person;
            }
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();

        $repligard_class = $em->getClassMetadata('midgard:midgard_repligard')->getName();
        if (!($entity instanceof $repligard_class))
        {
            $repligard_entry = $em->getRepository('midgard:midgard_repligard')->findOneBy(array('guid' => $entity->guid));
            $repligard_entry->object_action = self::ACTION_PURGE;
            $em->persist($repligard_entry);
        }
    }

    /**
     * This is essentially a workaround for http://www.doctrine-project.org/jira/browse/DBAL-642
     * It makes sure we get auto increment behavior similar to msyql (i.e. IDs unique during table's lifetime)
     */
    public function onSchemaCreateTable(SchemaCreateTableEventArgs $args)
    {
        $platform = $args->getPlatform();
        if ($platform->getName() !== 'sqlite')
        {
            return;
        }
        $table = $args->getTable();
        $columns = $args->getColumns();
        $options = $args->getOptions();
        $args->preventDefault();

        $name = str_replace('.', '__', $table->getName());
        $queryFields = $platform->getColumnDeclarationListSQL($columns);
        $queryFields = preg_replace('/^id INTEGER NOT NULL/', 'id INTEGER PRIMARY KEY AUTOINCREMENT', $queryFields);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints']))
        {
            foreach ($options['uniqueConstraints'] as $name => $definition)
            {
                $queryFields .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['foreignKeys']))
        {
            foreach ($options['foreignKeys'] as $foreignKey)
            {
                $queryFields .= ', ' . $platform->getForeignKeyDeclarationSQL($foreignKey);
            }
        }

        $args->addSql('CREATE TABLE ' . $name . ' (' . $queryFields . ')');

        if (isset($options['alter']) && true === $options['alter'])
        {
            return;
        }

        if (isset($options['indexes']) && ! empty($options['indexes']))
        {
            foreach ($options['indexes'] as $indexDef)
            {
                $args->addSql($platform->getCreateIndexSQL($indexDef, $name));
            }
        }

        if (isset($options['unique']) && ! empty($options['unique']))
        {
            foreach ($options['unique'] as $indexDef)
            {
                $args->addSql($platform->getCreateIndexSQL($indexDef, $name));
            }
        }
        return;
    }

    /**
     * This is a workaround for ENUM fields read from existing Midgard databases,
     * Like in the XML reader, they are converted to string for now
     */
    public function onSchemaColumnDefinition(SchemaColumnDefinitionEventArgs $args)
    {
        $column = array_change_key_case($args->getTableColumn(), CASE_LOWER);
        $type = strtok($column['type'], '()');
        if ($type == 'enum')
        {
            $args->preventDefault();

            $options = array
            (
                'length' => 255,
                'default' => isset($column['default']) ? $column['default'] : null,
                'notnull' => (bool) ($column['null'] != 'YES'),
                'comment' => $column['type']
            );

            $args->setColumn(new Column($column['field'], Type::getType(Type::STRING), $options));
        }
    }

    public function getSubscribedEvents()
    {
        return array(Events::prePersist, Events::preUpdate, Events::preRemove,
                     dbal_events::onSchemaCreateTable, dbal_events::onSchemaColumnDefinition);
    }
}