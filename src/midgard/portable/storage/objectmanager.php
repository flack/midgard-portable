<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage;

use midgard\portable\api\dbobject;
use midgard\portable\storage\metadata\entity;
use Doctrine\ORM\EntityManager;
use Doctrine\Common\Util\ClassUtils;
use midgard_datetime;
use Doctrine\ORM\UnitOfWork;

class objectmanager
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function create(dbobject $entity)
    {
        foreach ($this->em->getClassMetadata(get_class($entity))->getAssociationNames() as $name)
        {
            if (!empty($entity->$name))
            {
                //This makes sure that we don't have stale references
                $entity->$name = $entity->$name;
            }
        }

        //workaround for possible oid collisions in UnitOfWork
        //see http://www.doctrine-project.org/jira/browse/DDC-2785
        if ($this->em->getUnitOfWork()->getEntityState($entity) != UnitOfWork::STATE_NEW)
        {
            $this->em->detach($entity);
        }

        $this->em->persist($entity);
        $this->em->flush($entity);
        $this->em->detach($entity);
    }

    public function update(dbobject $entity)
    {
        $merged = $this->em->merge($entity);
        $this->copy_associations($entity, $merged);
        $this->em->persist($merged);
        $this->em->flush($merged);
        $this->em->detach($entity);
        $this->copy_metadata($merged, $entity);
    }

    /**
     * This is basically a workaround for some quirks when merging detached entities with changed associations
     *
     * @todo: This may or may not be a bug in Doctrine
     */
    private function copy_associations($source, $target)
    {
        foreach ($this->em->getClassMetadata(get_class($source))->getAssociationNames() as $name)
        {
            $target->$name = $source->$name;
        }
    }

    public function delete(dbobject $entity)
    {
        //we might deal with a proxy here, so we translate the classname
        $classname = ClassUtils::getRealClass(get_class($entity));
        $copy = new $classname($entity->id);

        $copy->metadata_deleted = true;

        foreach ($this->em->getClassMetadata($classname)->getAssociationNames() as $name)
        {
            if ($copy->$name === 0)
            {
                //This is necessary to kill potential proxy objects pointing to purged entities
                $copy->$name = 0;
            }
        }
        $copy = $this->em->merge($copy);

        $this->em->persist($copy);
        $this->em->flush($copy);
        $this->em->detach($entity);
        $this->copy_metadata($copy, $entity);
    }

    public function purge(dbobject $entity)
    {
        $this->em->getFilters()->disable('softdelete');
        $ref = $this->em->getReference(get_class($entity), $entity->id);
        $this->em->getFilters()->enable('softdelete');

        $this->em->remove($ref);
        $this->em->flush($ref);
        $this->em->detach($entity);
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
        $this->copy_metadata($ref, $entity);
    }

    public function unlock(dbobject $entity)
    {
        $user = connection::get_user();
        $ref = $this->em->getReference(get_class($entity), $entity->id);
        $ref->metadata_islocked = true;
        $ref->metadata_locker = $user->person;
        $ref->metadata_locked = new midgard_datetime;

        $this->em->persist($ref);
        $this->em->flush($ref);
        $this->em->detach($entity);
        $this->copy_metadata($ref, $entity);
    }

    private function copy_metadata($source, $target)
    {
        if (!$source instanceof entity)
        {
            return;
        }
        $target->metadata_deleted = $source->metadata_deleted;
        $target->metadata_revised = $source->metadata_revised;
        $target->metadata_revisor = $source->metadata_revisor;
        $target->metadata_revision = $source->metadata_revision;
    }
}