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
use midgard_datetime;

class objectmanager
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function update(dbobject $entity)
    {
        $merged = $this->em->merge($entity);
        $this->em->persist($merged);
        $this->em->flush($merged);
        $this->em->detach($entity);
        $this->copy_metadata($merged, $entity);
    }

    public function delete(dbobject $entity)
    {
        $ref = $this->em->getReference(get_class($entity), $entity->id);
        $ref->metadata_deleted = true;

        $this->em->persist($ref);
        $this->em->flush($ref);
        $this->em->detach($entity);
        $this->copy_metadata($ref, $entity);
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