<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage;

use midgard\portable\api\dbobject;
use Doctrine\ORM\EntityManager;

class objectmanager
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function update(dbobject $entity)
    {
        if (!$this->em->contains($entity))
        {
            $entity = $this->em->merge($entity);
        }
        $this->em->persist($entity);
        $this->em->flush($entity);
    }

    public function delete(dbobject $entity)
    {
        if (!$this->em->contains($entity))
        {
            $entity = $this->em->getReference(get_class($entity), $entity->id);
        }
        else
        {
            $this->em->refresh($entity);
        }
        $entity->metadata_deleted = true;

        $this->em->persist($entity);
        $this->em->flush($entity);
    }

    public function purge(dbobject $entity)
    {
        if (!$this->em->contains($entity))
        {
            $entity = $this->em->getReference(get_class($entity), $entity->id);
        }
        $this->em->remove($entity);
        $this->em->flush($entity);
    }
}