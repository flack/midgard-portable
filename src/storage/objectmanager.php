<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage;

use midgard\portable\api\dbobject;
use midgard\portable\api\error\exception;
use midgard\portable\storage\interfaces\metadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Common\Util\ClassUtils;
use midgard_datetime;

class objectmanager
{
    private readonly EntityManager $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function create(dbobject $entity)
    {
        foreach ($this->em->getClassMetadata(get_class($entity))->getAssociationNames() as $name) {
            if (!empty($entity->$name)) {
                //This makes sure that we don't have stale references
                $entity->$name = $entity->$name;
            }
        }

        //workaround for possible oid collisions in UnitOfWork
        //see https://github.com/doctrine/orm/issues/3037
        if ($this->em->getUnitOfWork()->getEntityState($entity) != UnitOfWork::STATE_NEW) {
            connection::log()->warning('oid collision during create detected, detaching ' . spl_object_hash($entity));
            $this->em->detach($entity);
        }

        $this->em->persist($entity);
        $this->em->flush($entity);
        $this->em->detach($entity);
        exception::ok();
    }

    public function update(dbobject $entity)
    {
        // if entities are loaded by querybuilder, they are managed at this point already,
        // which can in very rare (and very unreproducable) circumstances lead to the update
        // getting lost silently (because originalEntityData somehow is empty), so we detach
        // before doing anything else
        $this->em->detach($entity);
        $merged = $this->em->merge($entity);
        $this->copy_associations($entity, $merged);
        $this->em->persist($merged);
        $this->em->flush($merged);
        $this->em->detach($merged);
        $this->copy_metadata($merged, $entity);
        exception::ok();
    }

    /**
     * This is basically a workaround for some quirks when merging detached entities with changed associations
     *
     * @todo: This may or may not be a bug in Doctrine
     */
    private function copy_associations(dbobject $source, dbobject $target)
    {
        foreach ($this->em->getClassMetadata(get_class($source))->getAssociationNames() as $name) {
            $target->$name = $source->$name;
        }
    }

    private function kill_potential_proxies(dbobject $entity)
    {
        $classname = ClassUtils::getClass($entity);
        $cm = $this->em->getClassMetadata($classname);
        $changed_associations = $entity->__get_changed_associations();

        foreach ($cm->getAssociationNames() as $name) {
            if ($entity->$name === 0) {
                //This is necessary to kill potential proxy objects pointing to purged entities
                $entity->$name = 0;
            } elseif (!isset($changed_associations[$name])) {
                $value = $cm->getReflectionProperty($name)->getValue($entity);
                if ($value instanceof Proxy) {
                    //This makes sure that the associated entity doesn't end up in the changeset calculation
                    $value->__isInitialized__ = false;
                    continue;
                }
            }
        }
    }

    public function delete(dbobject $entity)
    {
        //we might deal with a proxy here, so we translate the classname
        $classname = ClassUtils::getClass($entity);
        $copy = new $classname($entity->id);

        //workaround for possible oid collisions in UnitOfWork
        //see https://github.com/doctrine/orm/issues/3037
        if ($this->em->getUnitOfWork()->getEntityState($copy) != UnitOfWork::STATE_DETACHED) {
            connection::log()->warning('oid collision during delete detected, detaching ' . spl_object_hash($copy));
            $this->em->detach($copy);
        }

        $copy = $this->em->merge($copy);
        $this->kill_potential_proxies($copy);
        $copy->metadata_deleted = true;

        $this->em->persist($copy);
        $this->em->flush($copy);
        $this->em->detach($copy);
        $this->em->detach($entity);
        $this->copy_metadata($copy, $entity, 'delete');
        exception::ok();
    }

    public function undelete(dbobject $entity)
    {
        $entity->metadata_deleted = false;
        $this->kill_potential_proxies($entity);

        $this->em->persist($entity);
        $this->em->flush($entity);
        $this->em->detach($entity);
    }

    public function purge(dbobject $entity)
    {
        $this->em->getFilters()->disable('softdelete');
        try {
            $entity = $this->em->merge($entity);
        } finally {
            $this->em->getFilters()->enable('softdelete');
        }
        $this->em->remove($entity);
        $this->em->flush($entity);
        $this->em->detach($entity);
        exception::ok();
    }

    public function approve(dbobject $entity)
    {
        $user = connection::get_user();
        $ref = $this->em->getReference(get_class($entity), $entity->id);
        $ref->metadata_isapproved = true;
        $ref->metadata_approver = $user->person;
        $ref->metadata_approved = new midgard_datetime;

        $this->em->persist($ref);
        $this->em->flush($ref);
        $this->em->detach($entity);
        $this->copy_metadata($ref, $entity, 'approve');
        exception::ok();
    }

    public function unapprove(dbobject $entity)
    {
        $user = connection::get_user();
        $ref = $this->em->getReference(get_class($entity), $entity->id);
        $ref->metadata_isapproved = false;
        $ref->metadata_approver = $user->person;
        $ref->metadata_approved = new midgard_datetime;

        $this->em->persist($ref);
        $this->em->flush($ref);
        $this->em->detach($entity);
        $this->copy_metadata($ref, $entity, 'approve');
        exception::ok();
    }

    public function lock(dbobject $entity)
    {
        $user = connection::get_user();
        $ref = $this->em->getReference(get_class($entity), $entity->id);
        $ref->metadata_islocked = true;
        $ref->metadata_locker = $user->person;
        $ref->metadata_locked = new midgard_datetime;

        $this->em->persist($ref);
        $this->em->flush($ref);
        $this->em->detach($entity);
        $this->copy_metadata($ref, $entity, 'lock');
        exception::ok();
    }

    public function unlock(dbobject $entity)
    {
        $ref = $this->em->getReference(get_class($entity), $entity->id);
        $ref->metadata_islocked = false;

        $this->em->persist($ref);
        $this->em->flush($ref);
        $this->em->detach($entity);
        $this->copy_metadata($ref, $entity, 'lock');
        exception::ok();
    }

    public function new_instance(string $classname) : dbobject
    {
        //workaround for possible oid collisions in UnitOfWork
        //see https://github.com/doctrine/orm/issues/3037
        $counter = 0;
        $candidates = [];
        do {
            $entity = new $classname;
            if ($counter++ > 100) {
                throw new exception('Failed to create fresh ' . $classname . ' instance (all tried oids are already known to UoW)');
            }
            //we keep the entity in memory to make sure we get a different oid during the next iteration
            $candidates[] = $entity;
        } while ($this->em->getUnitOfWork()->getEntityState($entity) !== UnitOfWork::STATE_NEW);
        // TODO: Calling $em->getUnitOfWork()->isInIdentityMap($entity) returns false in the same situation. Why?
        return $entity;
    }

    private function copy_metadata(dbobject $source, dbobject $target, string $action = 'update')
    {
        if (!$source instanceof metadata) {
            return;
        }

        $target->metadata_revised = $source->metadata_revised;
        $target->metadata_revisor = $source->metadata_revisor;
        $target->metadata_revision = $source->metadata_revision;

        if ($action == 'lock') {
            $target->metadata_islocked = $source->metadata_islocked;
            $target->metadata_locker = $source->metadata_locker;
            $target->metadata_locked = $source->metadata_locked;
        } elseif ($action == 'approve') {
            $target->metadata_isapproved = $source->metadata_isapproved;
            $target->metadata_approver = $source->metadata_approver;
            $target->metadata_approved = $source->metadata_approved;
        } elseif ($action == 'delete') {
            $target->metadata_deleted = $source->metadata_deleted;
        }
    }
}
