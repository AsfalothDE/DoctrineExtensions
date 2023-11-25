<?php

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Tree\Entity\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gedmo\Tree\RepositoryInterface;
use Gedmo\Tree\Traits\Repository\ORM\TreeRepositoryTrait;

/**
 * @template T of object
 *
 * @template-extends EntityRepository<T>
 *
 * @template-implements RepositoryInterface<T>
 */
abstract class AbstractTreeRepository extends EntityRepository implements RepositoryInterface
{
    use TreeRepositoryTrait;

    /** @param ClassMetadata<T> $class */
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);

        $this->initializeTreeRepository($em, $class);
    }
}
