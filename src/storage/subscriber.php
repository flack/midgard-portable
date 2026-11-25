<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage;

use midgard\portable\storage\interfaces\metadata;
use midgard\portable\api\dbobject;
use midgard\portable\api\repligard;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class subscriber implements EventSubscriber
{
    const ACTION_NONE = 0;
    const ACTION_DELETE = 1;
    const ACTION_PURGE = 2;
    const ACTION_CREATE = 3;
    const ACTION_UPDATE = 4;

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->on_create($entity, $em);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->on_update($entity, $em);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->on_remove($entity, $em);
        }
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if ($entity instanceof dbobject) {
            $om = $args->getObjectManager();
            $entity->injectObjectManager($om, $om->getClassMetadata(get_class($entity)));
        }
    }

    private function on_create(dbobject $entity, EntityManagerInterface $em)
    {
        $cm = $em->getClassMetadata(get_class($entity));
        if (!($entity instanceof repligard)) {
            if (empty($entity->guid)) {
                $entity->set_guid(connection::generate_guid());
                $em->getUnitOfWork()->recomputeSingleEntityChangeSet($cm, $entity);
            }

            $om = new objectmanager($em);
            $repligard_cm = $em->getClassMetadata(connection::get_fqcn('midgard_repligard'));
            $repligard_entry = $om->new_instance($repligard_cm->getName());
            $repligard_entry->typename = $cm->getReflectionClass()->getShortName();
            $repligard_entry->guid = $entity->guid;
            $repligard_entry->object_action = self::ACTION_CREATE;
            $em->persist($repligard_entry);
            $em->getUnitOfWork()->computeChangeSet($repligard_cm, $repligard_entry);
        }

        if ($entity instanceof metadata) {
            $entity->metadata->created = new \midgard_datetime();
            // we copy here instead of creating a new, because otherwise we might have
            // a one second difference if the code runs at the right millisecond
            $entity->metadata->revised = $entity->metadata->created;
            if ($user = connection::get_user()) {
                $entity->metadata_creator = $user->person;
                $entity->metadata_revisor = $user->person;
            }
            $entity->metadata->size = $this->calculate_size($cm, $entity);
            $em->getUnitOfWork()->recomputeSingleEntityChangeSet($cm, $entity);
        }
    }

    private function on_update(dbobject $entity, EntityManagerInterface $em)
    {
        if ($entity instanceof repligard) {
            return;
        }
        $check_repligard = true;
        $deleted = false;
        if ($entity instanceof metadata) {
            $deleted = $entity->{metadata::DELETED_FIELD};
            $cs = $em->getUnitOfWork()->getEntityChangeSet($entity);
            // We only need to update repligard if we're coming from create (revision 0)
            // or if we delete/undelete
            if (   !array_key_exists('metadata_deleted', $cs)
                && $entity->metadata_revision > 0)  {
                $check_repligard = false;
            }

            $create_revision = true;
            if (array_key_exists('metadata_islocked', $cs)) {
                $lock_fields = array_flip(['metadata_locked', 'metadata_islocked', 'metadata_locker']);
                $create_revision = !empty(array_diff_key($cs, $lock_fields));
            }

            if ($create_revision) {
                $cm = $em->getClassMetadata(get_class($entity));
                $entity->metadata_revised = new \midgard_datetime();
                $entity->metadata_revision++;
                if ($user = connection::get_user()) {
                    $entity->metadata_revisor = $user->person;
                }
                $entity->metadata->size = $this->calculate_size($cm, $entity);
                $em->getUnitOfWork()->recomputeSingleEntityChangeSet($cm, $entity);
            }
        }

        if ($check_repligard) {
            $repligard_entry = $em->getRepository(connection::get_fqcn('midgard_repligard'))->findOneBy(['guid' => $entity->guid]);

            if (empty($repligard_entry)) {
                connection::log()->error('No repligard entry found for GUID ' . $entity->guid);
            } else {
                $repligard_entry->object_action = $deleted ? self::ACTION_DELETE : self::ACTION_UPDATE;
                $em->persist($repligard_entry);
                $em->getUnitOfWork()->computeChangeSet($em->getClassMetadata(connection::get_fqcn('midgard_repligard')), $repligard_entry);
            }
        }
    }

    private function on_remove(dbobject $entity, EntityManagerInterface $em)
    {
        if (!($entity instanceof repligard)) {
            $repligard_entry = $em->getRepository(connection::get_fqcn('midgard_repligard'))->findOneBy(['guid' => $entity->guid]);
            if (empty($repligard_entry)) {
                connection::log()->error('No repligard entry found for GUID ' . $entity->guid);
            } else {
                $repligard_entry->object_action = self::ACTION_PURGE;
                $em->persist($repligard_entry);
                $em->getUnitOfWork()->computeChangeSet($em->getClassMetadata(connection::get_fqcn('midgard_repligard')), $repligard_entry);
            }
        }
    }

    private function calculate_size(ClassMetadata $cm, metadata $entity) : int
    {
        $size = 0;
        foreach ($cm->getAssociationNames() as $name) {
            if ($cm->midgard['links_as_entities']) {
                $size += strlen($entity->$name?->id ?? '');
            } else {
                $size += strlen($entity->$name);
            }
        }
        foreach ($cm->getFieldNames() as $name) {
            match ($cm->fieldMappings[$name]['type']) {
                Types::DATETIME_MUTABLE => $size += 19, // Y-m-d H:i:s
                Types::DATE_MUTABLE => $size += 10, // Y-m-d
                default => $size += strlen($entity->$name),
            };
        }
        return $size;
    }

    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $args)
    {
        $table = $args->getClassTable();
        $this->remove_foreign_keys($table);

        $platform = connection::get_em()->getConnection()->getDatabasePlatform();
        foreach ($table->getColumns() as $column) {
            if ($column->getComment()) {
                $this->apply_nonportable_dbtype($column, $platform);
            }
        }
    }

    /**
     * This is mostly a workaround for the fact that SchemaTool wants to create FKs on
     * each run since it doesn't detect that MyISAM tables don't support them
     *
     * @see https://github.com/doctrine/orm/issues/4270
     */
    private function remove_foreign_keys(Table $table)
    {
        if (   !$table->hasOption('engine')
            || $table->getOption('engine') !== 'MyISAM') {
            return;
        }
        foreach ($table->getForeignKeys() as $key) {
            $table->removeForeignKey($key->getName());
        }
    }

    /**
     * The driver passes dbtypes Doctrine can't handle as comment
     */
    private function apply_nonportable_dbtype(Column $column, AbstractPlatform $platform)
    {
        $comment = $column->getComment();
        $is_binary = $comment === 'BINARY';
        if (!$is_binary && !str_starts_with($comment, 'set(')) {
            return;
        }

        if (!($platform instanceof AbstractMySQLPlatform)) {
            // Nothing to do, comparison is binary by default in SQLite, and SET doesn't exist
            $column->setComment(null);
            return;
        }

        $config = $column->toArray();
        // BINARY selects the _bin collation for whichever charset the table uses
        $definition = $is_binary ? $column->getType()->getSQLDeclaration($config, $platform) . ' BINARY' : $comment;
        $definition .= $platform->getDefaultValueDeclarationSQL($config);
        if ($column->getNotnull()) {
            $definition .= ' NOT NULL';
        }
        $column->setColumnDefinition($definition . ' ' . $platform->getInlineColumnCommentSQL($comment));
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postLoad,
            Events::onFlush,
            ToolEvents::postGenerateSchemaTable
        ];
    }
}
