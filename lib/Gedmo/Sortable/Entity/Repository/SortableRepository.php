<?php

namespace Gedmo\Sortable\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gedmo\Sortable\SortableListener;
use Gedmo\Sortable\Traits\Repository\ORM\SortableRepositoryTrait;

/**
 * Sortable Repository
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class SortableRepository extends EntityRepository
{
    use SortableRepositoryTrait;

    public function __construct(EntityManager $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);

        $this->initializeSortableRepository($em, $class);
    }
}
