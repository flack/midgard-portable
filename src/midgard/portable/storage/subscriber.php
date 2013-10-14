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
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
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
            $ref = $em->getClassMetadata(get_class($entity))->getReflectionClass();
            $repligard_entry = new $repligard_class;
            $repligard_entry->guid = $entity->guid;
            $repligard_entry->typename = $ref->getShortName();
            $repligard_entry->object_action = self::ACTION_CREATE;
            $em->persist($repligard_entry);
        }

        if ($entity instanceof entity)
        {
            $entity->metadata->created->setTimestamp(time());
            $entity->metadata->revised->setTimestamp(time());
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

            if ($entity->metadata->deleted)
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
            $entity->metadata->revised->setTimestamp(time());
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

    public function getSubscribedEvents()
    {
        return array(Events::prePersist, Events::preUpdate, Events::preRemove);
    }
}