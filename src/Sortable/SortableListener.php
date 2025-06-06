<?php

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Sortable;

use Doctrine\Common\Comparable;
use Doctrine\Common\EventArgs;
use Doctrine\Deprecations\Deprecation;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Doctrine\Persistence\Event\ManagerEventArgs;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Gedmo\Mapping\MappedEventSubscriber;
use Gedmo\Sortable\Mapping\Event\SortableAdapter;
use Gedmo\Tool\ClassUtils;
use ProxyManager\Proxy\GhostObjectInterface;

/**
 * The SortableListener maintains a sort index on your entities
 * to enable arbitrary sorting.
 *
 * This behavior can impact the performance of your application
 * since it does some additional calculations on persisted objects.
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @phpstan-type SortableConfiguration = array{
 *   groups?: string[],
 *   position?: string,
 *   incrementBy: number,
 *   startWith: number,
 *   sortNullValues: boolean,
 *   useObjectClass?: class-string,
 * }
 * @phpstan-type SortableRelocation = array{
 *   name?: class-string,
 *   groups?: mixed[],
 *   deltas?: array<array{
 *     delta: int,
 *     exclude: int[],
 *     start: int,
 *     stop: int,
 *   }>,
 * }
 *
 * @phpstan-extends MappedEventSubscriber<SortableConfiguration, SortableAdapter>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class SortableListener extends MappedEventSubscriber
{
    /**
     * @var array<string, array<string, mixed>>
     *
     * @phpstan-var array<string, SortableRelocation>
     */
    private array $relocations = [];

    private bool $persistenceNeeded = false;

    /** @var array<string, int> */
    private array $maxPositions = [];

    /**
     * Specifies the list of events to listen
     *
     * @return string[]
     */
    public function getSubscribedEvents()
    {
        return [
            'onFlush',
            'loadClassMetadata',
            'prePersist',
            'postPersist',
            'preUpdate',
            'postRemove',
            'postFlush',
        ];
    }

    /**
     * Maps additional metadata
     *
     * @param LoadClassMetadataEventArgs $args
     *
     * @phpstan-param LoadClassMetadataEventArgs<ClassMetadata<object>, ObjectManager> $args
     *
     * @return void
     */
    public function loadClassMetadata(EventArgs $args)
    {
        $ea = $this->getEventAdapter($args);
        $this->loadMetadataForObjectClass($ea->getObjectManager(), $args->getClassMetadata());
    }

    /**
     * Collect position updates on objects being updated during flush
     * if they require changing.
     *
     * Persisting of positions is done later during prePersist, preUpdate and postRemove
     * events, otherwise the queries won't be executed within the transaction.
     *
     * The synchronization of the objects in memory is done in postFlush. This
     * ensures that the positions have been successfully persisted to database.
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     *
     * @return void
     */
    public function onFlush(EventArgs $args)
    {
        $this->persistenceNeeded = true;

        $ea = $this->getEventAdapter($args);
        $om = $ea->getObjectManager();
        $uow = $om->getUnitOfWork();

        // process all objects being deleted
        foreach ($ea->getScheduledObjectDeletions($uow) as $object) {
            $meta = $om->getClassMetadata(get_class($object));
            if ($config = $this->getConfiguration($om, $meta->getName())) {
                $this->processDeletion($ea, $config, $meta, $object);
            }
        }

        $updateValues = [];
        $scheduledUpdates = $ea->getScheduledObjectUpdates($uow);
        $i = count($scheduledUpdates);
        // process all objects being updated
        foreach ($ea->getScheduledObjectUpdates($uow) as $object) {
            $meta = $om->getClassMetadata(get_class($object));
            if ($config = $this->getConfiguration($om, $meta->getName())) {
                $position = $meta->getReflectionProperty($config['position'])->getValue($object);
                $updateValues[$position ?? $i - 1] = [$ea, $config, $meta, $object];
            }
            --$i;
        }
        krsort($updateValues);
        foreach ($updateValues as [$ea, $config, $meta, $object]) {
            $this->processUpdate($ea, $config, $meta, $object);
        }

        // process all objects being inserted
        foreach ($ea->getScheduledObjectInsertions($uow) as $object) {
            $meta = $om->getClassMetadata(get_class($object));
            if ($config = $this->getConfiguration($om, $meta->getName())) {
                $this->processInsert($ea, $config, $meta, $object);
            }
        }
    }

    /**
     * Update maxPositions as needed
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     *
     * @return void
     */
    public function prePersist(EventArgs $args)
    {
        $ea = $this->getEventAdapter($args);
        $om = $ea->getObjectManager();
        $object = $ea->getObject();
        $meta = $om->getClassMetadata(get_class($object));

        if ($config = $this->getConfiguration($om, $meta->getName())) {
            // Get groups
            $groups = $this->getGroups($meta, $config, $object);

            // Get hash
            $hash = $this->getHash($groups, $config);

            // Get max position
            if (!isset($this->maxPositions[$hash])) {
                $this->maxPositions[$hash] = $this->getMaxPosition($ea, $meta, $config, $object);
            }
        }
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     *
     * @return void
     */
    public function postPersist(EventArgs $args)
    {
        // persist position updates here, so that the update queries
        // are executed within transaction
        $this->persistRelocations($this->getEventAdapter($args));
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     *
     * @return void
     */
    public function preUpdate(EventArgs $args)
    {
        // persist position updates here, so that the update queries
        // are executed within transaction
        $this->persistRelocations($this->getEventAdapter($args));
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     *
     * @return void
     */
    public function postRemove(EventArgs $args)
    {
        // persist position updates here, so that the update queries
        // are executed within transaction
        $this->persistRelocations($this->getEventAdapter($args));
    }

    /**
     * Sync objects in memory
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     *
     * @return void
     */
    public function postFlush(EventArgs $args)
    {
        $ea = $this->getEventAdapter($args);
        $em = $ea->getObjectManager();

        $updatedObjects = [];

        foreach ($this->relocations as $hash => $relocation) {
            $config = $this->getConfiguration($em, $relocation['name']);
            foreach ($relocation['deltas'] as $delta) {
                if ($delta['start'] > $this->maxPositions[$hash] || 0 == $delta['delta']) {
                    continue;
                }

                $meta = $em->getClassMetadata($relocation['name']);

                // now walk through the unit of work in memory objects and sync those
                $uow = $em->getUnitOfWork();
                foreach ($uow->getIdentityMap() as $className => $objects) {
                    // for inheritance mapped classes, only root is always in the identity map
                    if ($className !== $ea->getRootObjectClass($meta) || !$this->getConfiguration($em, $className)) {
                        continue;
                    }
                    foreach ($objects as $object) {
                        if ($object instanceof GhostObjectInterface && !$object->isProxyInitialized()) {
                            continue;
                        }

                        $changeSet = $ea->getObjectChangeSet($uow, $object);

                        // if the entity's position is already changed, stop now
                        if (array_key_exists($config['position'], $changeSet)) {
                            continue;
                        }

                        // if the entity's group has changed, we stop now
                        $groups = $this->getGroups($meta, $config, $object);
                        foreach (array_keys($groups) as $group) {
                            if (array_key_exists($group, $changeSet)) {
                                continue 2;
                            }
                        }

                        $oid = spl_object_id($object);
                        $pos = $meta->getReflectionProperty($config['position'])->getValue($object);
                        $matches = $pos >= $delta['start'];
                        $matches = $matches && ($delta['stop'] <= $config['startWith'] || $pos < $delta['stop']);
                        $value = reset($relocation['groups']);
                        while ($matches && ($group = key($relocation['groups']))) {
                            $gr = $meta->getReflectionProperty($group)->getValue($object);
                            if (null === $value) {
                                $matches = null === $gr;
                            } elseif (is_object($gr) && is_object($value) && $gr !== $value) {
                                // Special case for equal objects but different instances.
                                // If the object implements Comparable interface we can use its compareTo method
                                // Otherwise we fallback to normal object comparison
                                if ($gr instanceof Comparable) {
                                    $matches = $gr->compareTo($value);
                                    // @todo: Remove "is_int" check and only support integer as the interface expects.
                                    if (is_int($matches)) {
                                        $matches = 0 === $matches;
                                    } else {
                                        Deprecation::trigger(
                                            'gedmo/doctrine-extensions',
                                            'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2542',
                                            'Support for "%s" as return type from "%s::compareTo()" is deprecated since'
                                            .' gedmo/doctrine-extensions 3.11 and will be removed in version 4.0. Return "integer" instead.',
                                            gettype($matches),
                                            Comparable::class
                                        );
                                    }
                                } else {
                                    $matches = $gr == $value;
                                }
                            } else {
                                $matches = $gr === $value;
                            }
                            $value = next($relocation['groups']);
                        }
                        if ($matches) {
                            // We cannot use `$this->setFieldValue()` here, because it will create a change set, that will
                            // prevent from other relocations being executed on this object.
                            // We just update the object value and will create the change set later.
                            if (!isset($updatedObjects[$oid])) {
                                $updatedObjects[$oid] = [
                                    'object' => $object,
                                    'field' => $config['position'],
                                    'oldValue' => $pos,
                                ];
                            }
                            $updatedObjects[$oid]['newValue'] = $pos + $delta['delta'];

                            $meta->getReflectionProperty($config['position'])->setValue($object, $updatedObjects[$oid]['newValue']);
                        }
                    }
                }
            }

            foreach ($updatedObjects as $updateData) {
                $this->setFieldValue($ea, $updateData['object'], $updateData['field'], $updateData['oldValue'], $updateData['newValue']);
            }

            // Clear relocations
            // unset only if relocations has been processed
            unset($this->relocations[$hash], $this->maxPositions[$hash]);
        }
    }

    /**
     * Computes node positions and updates the sort field in memory and in the db
     *
     * @param array<string, mixed>  $config
     * @param ClassMetadata<object> $meta
     * @param object                $object
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return void
     */
    protected function processInsert(SortableAdapter $ea, array $config, $meta, $object)
    {
        $old = $meta->getReflectionProperty($config['position'])->getValue($object);
        $newPosition = $meta->getReflectionProperty($config['position'])->getValue($object);

        if (null === $newPosition) {
            $newPosition = $config['startWith'] - 1;
        }

        // Get groups
        $groups = $this->getGroups($meta, $config, $object);

        // Get hash
        $hash = $this->getHash($groups, $config);

        // Get max position
        if (!isset($this->maxPositions[$hash])) {
            $this->maxPositions[$hash] = $this->getMaxPosition($ea, $meta, $config, $object);
        }

        // Only calculate position when
        if (null !== $this->maxPositions[$hash]) {
            // Compute position if it is negative
            if ($newPosition < $config['startWith']) {
                $newPosition += $this->maxPositions[$hash];
                $newPosition += 2 - $config['startWith']; // position == -1 => append at end of list // Todo: use incrementby instead of +2
                if ($newPosition < $config['startWith']) {
                    $newPosition = $config['startWith'];
                }
            }

            // Set position to max position if it is too big
            $newPosition = min([$this->maxPositions[$hash] + 1, $newPosition]); // Todo: use increment by for + 1

            // Compute relocations
            // New inserted entities should not be relocated by position update, so we exclude it.
            // Otherwise, they could be relocated unintentionally.
            $relocation = [$hash, $config['useObjectClass'], $groups, $newPosition, -1, +1, [$object]]; // Todo: use increment by for +1 and -1

            // Apply existing relocations
            $applyDelta = 0;
            if (isset($this->relocations[$hash])) {
                foreach ($this->relocations[$hash]['deltas'] as $delta) {
                    if ($delta['start'] <= $newPosition
                        && ($delta['stop'] > $newPosition || $delta['stop'] < $config['startWith'])) {
                        $applyDelta += $delta['delta'];
                    }
                }
            }
            $newPosition += $applyDelta;

            // Add relocations
            call_user_func_array([$this, 'addRelocation'], $relocation);

            // Set new position
            if ($old < $config['startWith'] || null === $old) {
                $this->setFieldValue($ea, $object, $config['position'], $old, $newPosition);
            }
        }
    }

    /**
     * Computes node positions and updates the sort field in memory and in the db
     *
     * @param array<string, mixed>  $config
     * @param ClassMetadata<object> $meta
     * @param object                $object
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return void
     */
    protected function processUpdate(SortableAdapter $ea, array $config, $meta, $object)
    {
        $em = $ea->getObjectManager();
        $uow = $em->getUnitOfWork();

        $changed = false;
        $groupHasChanged = false;
        $changeSet = $ea->getObjectChangeSet($uow, $object);

        // Get groups
        $groups = $this->getGroups($meta, $config, $object);

        // handle old groups
        $oldGroups = $groups;
        foreach (array_keys($groups) as $group) {
            if (array_key_exists($group, $changeSet)) {
                $changed = true;
                $oldGroups[$group] = $changeSet[$group][0];
            }
        }

        $oldPosition = $config['startWith'];

        if (!isset($config['sortNullValues']) || $config['sortNullValues']) {
            $newPosition = $config['startWith'];
        }

        if ($changed) {
            $oldHash = $this->getHash($oldGroups, $config);
            $this->maxPositions[$oldHash] = $this->getMaxPosition($ea, $meta, $config, $object, $oldGroups);
            if (array_key_exists($config['position'], $changeSet)) {
                $oldPosition = $changeSet[$config['position']][0];
            } else {
                $oldPosition = $meta->getReflectionProperty($config['position'])->getValue($object);
            }
            $this->addRelocation($oldHash, $config['useObjectClass'], $oldGroups, $oldPosition + 1, $this->maxPositions[$oldHash] + 1, $config['startWith'] - 1);
            $groupHasChanged = true;
        }

        // Get hash
        $hash = $this->getHash($groups, $config);

        // Get max position
        if (!isset($this->maxPositions[$hash])) {
            $this->maxPositions[$hash] = $this->getMaxPosition($ea, $meta, $config, $object);
        }

        if (array_key_exists($config['position'], $changeSet)) {
            if ($changed && $config['startWith'] - 1 === $this->maxPositions[$hash]) {
                // position has changed
                // the group of element has changed
                // and the target group has no children before
                $oldPosition = $config['startWith'] - 1;
                $newPosition = $config['startWith'] - 1;
            } else {
                // position was manually updated
                $oldPosition = $changeSet[$config['position']][0];
                $newPosition = $changeSet[$config['position']][1];
                $changed = $changed || $oldPosition != $newPosition;
            }
        } elseif ($changed) {
            $newPosition = $oldPosition;
        }

        if ($groupHasChanged) {
            $oldPosition = $config['startWith'] - 1;
        }
        if (!$changed) {
            return;
        }

        if (null !== $this->maxPositions[$hash]) {
            // Compute position if it is negative
            if ($newPosition < $config['startWith']) {
                if ($config['startWith'] - 1 === $oldPosition) {
                    $newPosition += $this->maxPositions[$hash];
                    $newPosition += 2 - $config['startWith']; // position == -1 => append at end of list // Todo: use incrementby
                } else {
                    $newPosition += $this->maxPositions[$hash] + 1; // position == -1 => append at end of list // Todo: use increment by
                }

                if ($newPosition < $config['startWith']) {
                    $newPosition = $config['startWith'];
                }
            } elseif ($newPosition > $this->maxPositions[$hash]) {
                if ($groupHasChanged) {
                    $newPosition = $this->maxPositions[$hash] + 1; // Todo: use increment by
                } else {
                    $newPosition = $this->maxPositions[$hash];
                }
            } else {
                $newPosition = min([$this->maxPositions[$hash], $newPosition]);
            }

            // Compute relocations
            /*
            CASE 1: shift backwards
            |----0----|----1----|----2----|----3----|----4----|
            |--node1--|--node2--|--node3--|--node4--|--node5--|
            Update node4: setPosition(1)
            --> Update position + 1 where position in [1,3)
            |--node1--|--node4--|--node2--|--node3--|--node5--|
            CASE 2: shift forward
            |----0----|----1----|----2----|----3----|----4----|
            |--node1--|--node2--|--node3--|--node4--|--node5--|
            Update node2: setPosition(3)
            --> Update position - 1 where position in (1,3]
            |--node1--|--node3--|--node4--|--node2--|--node5--|
            */
            $relocation = null;
            if ($config['startWith'] - 1 === $oldPosition) {
                // special case when group changes
                $relocation = [$hash, $config['useObjectClass'], $groups, $newPosition, $config['startWith'] - 1, +1]; // Todo: Use increment by
            } elseif ($newPosition < $oldPosition) {
                $relocation = [$hash, $config['useObjectClass'], $groups, $newPosition, $oldPosition, +1]; // Todo: Use increment by
            } elseif ($newPosition > $oldPosition) {
                $relocation = [$hash, $config['useObjectClass'], $groups, $oldPosition + 1, $newPosition + 1, -1]; // Todo: Use increment by
            }

            // Apply existing relocations (from old version of Sortable, apply it only when maxPositions is null, which indicates there was no group/sorting earlier)
            $applyDelta = 0;
            if (isset($this->relocations[$hash]) && 0 === $this->maxPositions[$hash]) {
                foreach ($this->relocations[$hash]['deltas'] as $delta) {
                    if ($delta['start'] <= $newPosition
                        && ($delta['stop'] > $newPosition || $delta['stop'] < $config['startWith'])) {
                        $applyDelta += $delta['delta'];
                    }
                }
            }
            $newPosition += $applyDelta;

            if ($relocation) {
                // Add relocation
                call_user_func_array([$this, 'addRelocation'], $relocation);
            }
        } else {
            $newPosition = null;
        }

        // Set new position
        $this->setFieldValue($ea, $object, $config['position'], $oldPosition, $newPosition);
    }

    /**
     * Computes node positions and updates the sort field in memory and in the db
     *
     * @param array<string, mixed>  $config
     * @param ClassMetadata<object> $meta
     * @param object                $object
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return void
     */
    protected function processDeletion(SortableAdapter $ea, array $config, $meta, $object)
    {
        $position = $meta->getReflectionProperty($config['position'])->getValue($object);

        // Get groups
        $groups = $this->getGroups($meta, $config, $object);

        // Get hash
        $hash = $this->getHash($groups, $config);

        // Get max position
        if (!isset($this->maxPositions[$hash])) {
            $this->maxPositions[$hash] = $this->getMaxPosition($ea, $meta, $config, $object);
        }

        if (is_int($position)) {
            // Add relocation
            $this->addRelocation($hash, $config['useObjectClass'], $groups, $position, $config['startWith'] - 1, -1); // TODO: Use increment by -$config['incrementBy']
        }
    }

    /**
     * Persists relocations to database.
     *
     * @return void
     */
    protected function persistRelocations(SortableAdapter $ea)
    {
        if (!$this->persistenceNeeded) {
            return;
        }

        $em = $ea->getObjectManager();
        foreach ($this->relocations as $hash => $relocation) {
            $config = $this->getConfiguration($em, $relocation['name']);
            foreach ($relocation['deltas'] as $delta) {
                if ($delta['start'] > $this->maxPositions[$hash] || 0 == $delta['delta']) {
                    continue;
                }
                $ea->updatePositions($relocation, $delta, $config);
            }
        }

        $this->persistenceNeeded = false;
    }

    /**
     * @param array<string, mixed> $groups
     * @param array<string, mixed> $config
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return string
     */
    protected function getHash($groups, array $config)
    {
        $data = $config['useObjectClass'];
        foreach ($groups as $group => $val) {
            if ($val instanceof \DateTime) {
                $val = $val->format('c');
            } elseif (is_object($val)) {
                $val = spl_object_id($val);
            }
            $data .= $group.$val;
        }

        return md5($data);
    }

    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     * @param object                $object
     * @param array<string, mixed>  $groups
     *
     * @phpstan-param SortableConfiguration $config
     */
    protected function getMaxPosition(SortableAdapter $ea, $meta, $config, $object, array $groups = []): ?int
    {
        $em = $ea->getObjectManager();
        $uow = $em->getUnitOfWork();
        $maxPos = null;

        // Get groups
        if ([] === $groups) {
            $groups = $this->getGroups($meta, $config, $object);
        }

        // Get hash
        $hash = $this->getHash($groups, $config);

        // Check for cached max position
        if (isset($this->maxPositions[$hash])) {
            return $this->maxPositions[$hash];
        }

        // Check for groups that are associations. If the value is an object and is
        // scheduled for insert, it has no identifier yet and is obviously new
        // see issue #226
        foreach ($groups as $val) {
            if (is_object($val) && ($uow->isScheduledForInsert($val) || !$em->getMetadataFactory()->isTransient(ClassUtils::getClass($val)) && $uow::STATE_MANAGED !== $ea->getObjectState($uow, $val))) {
                return $config['startWith'] - 1;
            }
        }

        $maxPos = $ea->getMaxPosition($config, $meta, $groups);

        // The value of the group field is null so there is nothing to sort by, position should be null
        if (false === $maxPos && !$config['sortNullValues']) {
            return null;
        }

        // No entries there yet, start with counting
        if (null === $maxPos) {
            $maxPos = $config['startWith'] - 1;
        }

        return (int) $maxPos;
    }

    /**
     * Add a relocation rule
     *
     * @param string                $hash    The hash of the sorting group
     * @param string                $class   The object class
     * @param array<string, object> $groups  The sorting groups
     * @param int                   $start   Inclusive index to start relocation from
     * @param int                   $stop    Exclusive index to stop relocation at
     * @param int                   $delta   The delta to add to relocated nodes
     * @param array<int, object>    $exclude Objects to be excluded from relocation
     *
     * @phpstan-param class-string $class
     *
     * @return void
     */
    protected function addRelocation($hash, $class, $groups, $start, $stop, $delta, array $exclude = [])
    {
        if (!array_key_exists($hash, $this->relocations)) {
            $this->relocations[$hash] = ['name' => $class, 'groups' => $groups, 'deltas' => []];
        }

        try {
            $newDelta = ['start' => $start, 'stop' => $stop, 'delta' => $delta, 'exclude' => $exclude];
            array_walk($this->relocations[$hash]['deltas'], static function (&$val, $idx, $needle) {
                if ($val['start'] == $needle['start'] && $val['stop'] == $needle['stop']) {
                    $val['delta'] += $needle['delta'];
                    $val['exclude'] = array_merge($val['exclude'], $needle['exclude']);

                    throw new \Exception('Found delta. No need to add it again.');
                }

                // For every deletion relocation add newly created object to the list of excludes
                // otherwise position update queries will run for created objects as well.
                if (-1 == $val['delta'] && 1 == $needle['delta']) {
                    $val['exclude'] = array_merge($val['exclude'], $needle['exclude']);
                }
            }, $newDelta);
            $this->relocations[$hash]['deltas'][] = $newDelta;
        } catch (\Exception $e) {
        }
    }

    /**
     * @param ClassMetadata<object>                $meta
     * @param array<string, array<string, string>> $config
     * @param object                               $object
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return array<string, mixed>
     */
    protected function getGroups($meta, $config, $object)
    {
        $groups = [];
        if (isset($config['groups'])) {
            foreach ($config['groups'] as $group) {
                $groups[$group] = $meta->getReflectionProperty($group)->getValue($object);
            }
        }

        return $groups;
    }

    protected function getNamespace()
    {
        return __NAMESPACE__;
    }
}
