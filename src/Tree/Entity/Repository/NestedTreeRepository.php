<?php

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Tree\Entity\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gedmo\Tree\Traits\Repository\ORM\NestedTreeRepositoryTrait;

/**
 * The NestedTreeRepository has some useful functions
 * to interact with NestedSet tree. Repository uses
 * the strategy used by listener
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @template T of object
 *
 * @template-extends AbstractTreeRepository<T>
 */
class NestedTreeRepository extends AbstractTreeRepository
{
    use NestedTreeRepositoryTrait;

    /**
     * {@inheritdoc}
     */
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);

        $this->initializeTreeRepository($em, $class);
    }
}
