<?php

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Sortable\Mapping\Event\Adapter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;
use Gedmo\Sortable\Mapping\Event\SortableAdapter;
use Gedmo\Sortable\SortableListener;

/**
 * Doctrine event adapter for ORM adapted
 * for sortable behavior
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @phpstan-import-type SortableConfiguration from SortableListener
 * @phpstan-import-type SortableRelocation from SortableListener
 */
final class ORM extends BaseAdapterORM implements SortableAdapter
{
    /**
     * @param array<string, mixed>    $config
     * @param ClassMetadata<object>   $meta
     * @param iterable<string, mixed> $groups
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return int|bool|null
     */
    public function getMaxPosition(array $config, $meta, $groups)
    {
        $em = $this->getObjectManager();

        $qb = $em->createQueryBuilder();
        $qb->select('MAX(n.'.$config['position'].')')
           ->from($config['useObjectClass'], 'n');

        $addGroupWhere = $this->addGroupWhere($qb, $meta, $groups, $config);

        if ($groups && empty($addGroupWhere)) {
            // no need to run query when the group to query by has the value null
            return false;
        }

        foreach ($addGroupWhere as $where => $parameter) {
            $qb->andWhere($where);
            if ($parameter) {
                [$key, $value, $type] = $parameter;
                $qb->setParameter($key, $value, $type);
            }
        }

        $query = $qb->getQuery();
        $query->useQueryCache(false);
        $query->disableResultCache();
        $query->setMaxResults(1);

        return $query->getSingleScalarResult();
    }

    /**
     * @param array<string, mixed> $relocation
     * @param array<string, mixed> $delta
     * @param array<string, mixed> $config
     *
     * @phpstan-param SortableRelocation    $relocation
     * @phpstan-param SortableConfiguration $config
     *
     * @return void
     */
    public function updatePositions($relocation, $delta, $config)
    {
        $sign = $delta['delta'] < $config['startWith'] ? '-' : '+';
        $absDelta = abs($delta['delta']);
        $dql = "UPDATE {$relocation['name']} n";
        $dql .= " SET n.{$config['position']} = n.{$config['position']} {$sign} {$absDelta}";
        $dql .= " WHERE n.{$config['position']} >= {$delta['start']}";
        // if not null, false or 0
        if ($delta['stop'] > $config['startWith']) {
            $dql .= " AND n.{$config['position']} < {$delta['stop']}";
        }
        $i = $config['startWith'] - 1; // Todo: substract incrementBy value?
        $params = [];
        foreach ($relocation['groups'] as $group => $value) {
            if (null === $value) {
                if ($config['sortNullValues']) {
                    $dql .= " AND n.{$group} IS NULL";
                } else {
                    // no need to run query when the group to query by has the value null
                    continue;
                }
            } else {
                $dql .= " AND n.{$group} = :val___".(++$i); // Todo: incrementby instead of ++?
                $params['val___'.$i] = $value;
            }
        }

        // add excludes
        if (!empty($delta['exclude'])) {
            $meta = $this->getObjectManager()->getClassMetadata($relocation['name']);
            if (1 === count($meta->getIdentifier())) {
                // if we only have one identifier, we can use IN syntax, for better performance
                $excludedIds = [];
                foreach ($delta['exclude'] as $entity) {
                    if ($id = $meta->getFieldValue($entity, $meta->getIdentifier()[0])) {
                        $excludedIds[] = $id;
                    }
                }
                if (!empty($excludedIds)) {
                    $params['excluded'] = $excludedIds;
                    $dql .= " AND n.{$meta->getIdentifier()[0]} NOT IN (:excluded)";
                }
            } elseif (count($meta->getIdentifier()) > 1) {
                foreach ($delta['exclude'] as $entity) {
                    $j = 0;
                    $dql .= ' AND NOT (';
                    foreach ($meta->getIdentifierValues($entity) as $id => $value) {
                        $dql .= ($j > 0 ? ' AND ' : '')."n.{$id} = :val___".(++$i);
                        $params['val___'.$i] = $value;
                        ++$j;
                    }
                    $dql .= ')';
                }
            }
        }

        $em = $this->getObjectManager();
        $q = $em->createQuery($dql);
        $q->setParameters($params);
        $q->getSingleScalarResult();
    }

    /**
     * @param ClassMetadata<object>   $metadata
     * @param iterable<string, mixed> $groups
     */
    private function addGroupWhere(QueryBuilder $qb, ClassMetadata $metadata, iterable $groups, $config): array
    {
        $groupWhere = [];
        $i = 1;
        foreach ($groups as $group => $value) {
            if (null === $value) {
                $qb->andWhere($qb->expr()->isNull('n.'.$group));
                if ($config['sortNullValues']) {
                    $groupWhere[$qb->expr()->isNull('n.'.$group)] = null;
                } else {
                    // Don't sort when group value is null
                    continue;
                }
            } else {
                $groupWhere['n.'.$group.' = :group__'.$i] = ['group__'.$i, $value, $metadata->getTypeOfField($group)];
            }
            ++$i;
        }

        return $groupWhere;
    }
}
