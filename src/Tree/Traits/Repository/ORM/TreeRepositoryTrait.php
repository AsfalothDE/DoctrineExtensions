<?php

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Tree\Traits\Repository\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\InvalidMappingException;
use Gedmo\Tool\Wrapper\EntityWrapper;
use Gedmo\Tree\RepositoryUtils;
use Gedmo\Tree\RepositoryUtilsInterface;
use Gedmo\Tree\TreeListener;

trait TreeRepositoryTrait
{
    /**
     * Tree listener on event manager
     *
     * @var TreeListener
     */
    protected $listener;

    /**
     * Repository utils
     *
     * @var RepositoryUtilsInterface
     */
    protected $repoUtils;

    /**
     * This method should be called in your repository __construct().
     * Example:
     *
     * class MyTreeRepository extends EntityRepository
     * {
     *     use NestedTreeRepository; // or ClosureTreeRepository, or MaterializedPathRepository.
     *
     *     public function __construct(EntityManager $em, ClassMetadata $class)
     *     {
     *         parent::__construct($em, $class);
     *
     *         $this->initializeTreeRepository($em, $class);
     *     }
     *
     *     // ...
     * }
     */
    public function initializeTreeRepository(EntityManagerInterface $em, ClassMetadata $class)
    {
        $treeListener = null;
        foreach ($em->getEventManager()->getAllListeners() as $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof TreeListener) {
                    $treeListener = $listener;

                    break 2;
                }
            }
        }

        if (null === $treeListener) {
            throw new InvalidMappingException('Tree listener was not found on your entity manager, it must be hooked into the event manager');
        }

        $this->listener = $treeListener;
        if (!$this->validate()) {
            throw new InvalidMappingException('This repository cannot be used for tree type: '.$treeListener->getStrategy($em, $class->getName())->getName());
        }

        $this->repoUtils = new RepositoryUtils($this->getEntityManager(), $this->getClassMetadata(), $this->listener, $this);
    }

    /**
     * Sets the RepositoryUtilsInterface instance
     *
     * @return static
     */
    public function setRepoUtils(RepositoryUtilsInterface $repoUtils)
    {
        $this->repoUtils = $repoUtils;

        return $this;
    }

    /**
     * Returns the RepositoryUtilsInterface instance
     *
     * @return RepositoryUtilsInterface|null
     */
    public function getRepoUtils()
    {
        return $this->repoUtils;
    }

    public function childCount($node = null, $direct = false)
    {
        $meta = $this->getClassMetadata();

        if (is_object($node)) {
            if (!is_a($node, $meta->getName())) {
                throw new InvalidArgumentException('Node is not related to this repository');
            }

            $wrapped = new EntityWrapper($node, $this->getEntityManager());

            if (!$wrapped->hasValidIdentifier()) {
                throw new InvalidArgumentException('Node is not managed by UnitOfWork');
            }
        }

        $qb = $this->getChildrenQueryBuilder($node, $direct);

        // We need to remove the ORDER BY DQL part since some vendors could throw an error
        // in count queries
        $dqlParts = $qb->getDQLParts();

        // We need to check first if there's an ORDER BY DQL part, because resetDQLPart doesn't
        // check if its internal array has an "orderby" index
        if (isset($dqlParts['orderBy'])) {
            $qb->resetDQLPart('orderBy');
        }

        $aliases = $qb->getRootAliases();
        $alias = $aliases[0];

        $qb->select('COUNT('.$alias.')');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @see RepositoryUtilsInterface::childrenHierarchy
     */
    public function childrenHierarchy($node = null, $direct = false, array $options = [], $includeNode = false)
    {
        return $this->repoUtils->childrenHierarchy($node, $direct, $options, $includeNode);
    }

    /**
     * @see RepositoryUtilsInterface::buildTree
     */
    public function buildTree(array $nodes, array $options = [])
    {
        return $this->repoUtils->buildTree($nodes, $options);
    }

    /**
     * @see RepositoryUtilsInterface::buildTreeArray
     */
    public function buildTreeArray(array $nodes)
    {
        return $this->repoUtils->buildTreeArray($nodes);
    }

    /**
     * @see RepositoryUtilsInterface::setChildrenIndex
     */
    public function setChildrenIndex($childrenIndex)
    {
        $this->repoUtils->setChildrenIndex($childrenIndex);
    }

    /**
     * @see RepositoryUtilsInterface::getChildrenIndex
     */
    public function getChildrenIndex()
    {
        return $this->repoUtils->getChildrenIndex();
    }

    /**
     * Get all root nodes query builder
     *
     * @param string|string[]|null $sortByField Sort by field
     * @param string|string[]      $direction   Sort direction ("asc" or "desc")
     *
     * @return QueryBuilder QueryBuilder object
     */
    abstract public function getRootNodesQueryBuilder($sortByField = null, $direction = 'asc');

    /**
     * Get all root nodes query
     *
     * @param string|string[]|null $sortByField Sort by field
     * @param string|string[]      $direction   Sort direction ("asc" or "desc")
     *
     * @return Query Query object
     */
    abstract public function getRootNodesQuery($sortByField = null, $direction = 'asc');

    /**
     * Returns a QueryBuilder configured to return an array of nodes suitable for buildTree method
     *
     * @param object               $node        Root node
     * @param bool                 $direct      Obtain direct children?
     * @param array<string, mixed> $options     Options
     * @param bool                 $includeNode Include node in results?
     *
     * @return QueryBuilder QueryBuilder object
     */
    abstract public function getNodesHierarchyQueryBuilder($node = null, $direct = false, array $options = [], $includeNode = false);

    /**
     * Returns a Query configured to return an array of nodes suitable for buildTree method
     *
     * @param object               $node        Root node
     * @param bool                 $direct      Obtain direct children?
     * @param array<string, mixed> $options     Options
     * @param bool                 $includeNode Include node in results?
     *
     * @return Query Query object
     */
    abstract public function getNodesHierarchyQuery($node = null, $direct = false, array $options = [], $includeNode = false);

    /**
     * Get list of children followed by given $node. This returns a QueryBuilder object
     *
     * @param object|null          $node        If null, all tree nodes will be taken
     * @param bool                 $direct      True to take only direct children
     * @param string|string[]|null $sortByField Field name or array of fields names to sort by
     * @param string|string[]      $direction   Sort order ('asc'|'desc'|'ASC'|'DESC'). If $sortByField is an array, this may also be an array with matching number of elements
     * @param bool                 $includeNode Include the root node in results?
     *
     * @return QueryBuilder QueryBuilder object
     *
     * @phpstan-param 'asc'|'desc'|'ASC'|'DESC'|array<int, 'asc'|'desc'|'ASC'|'DESC'> $direction
     */
    abstract public function getChildrenQueryBuilder($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false);

    /**
     * Get list of children followed by given $node. This returns a Query
     *
     * @param object|null          $node        If null, all tree nodes will be taken
     * @param bool                 $direct      True to take only direct children
     * @param string|string[]|null $sortByField Field name or array of fields names to sort by
     * @param string|string[]      $direction   Sort order ('asc'|'desc'|'ASC'|'DESC'). If $sortByField is an array, this may also be an array with matching number of elements
     * @param bool                 $includeNode Include the root node in results?
     *
     * @return Query Query object
     *
     * @phpstan-param 'asc'|'desc'|'ASC'|'DESC'|array<int, 'asc'|'desc'|'ASC'|'DESC'> $direction
     */
    abstract public function getChildrenQuery($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false);

    /**
     * @return QueryBuilder
     */
    protected function getQueryBuilder()
    {
        return $this->getEntityManager()->createQueryBuilder();
    }

    /**
     * Checks if current repository is right
     * for currently used tree strategy
     *
     * @return bool
     */
    abstract protected function validate();

    /**
     * @return EntityManager
     */
    abstract protected function getEntityManager();

    /**
     * @return ClassMetadata
     */
    abstract protected function getClassMetadata();
}
