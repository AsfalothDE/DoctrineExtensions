<?php

namespace Gedmo\Tests\Sortable\Fixture;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Sortable\Entity\Repository\SortableRepository;

/**
 * @ORM\Entity(repositoryClass="Gedmo\Sortable\Entity\Repository\SortableRepository")
 */
#[ORM\Entity(repositoryClass: SortableRepository::class)]
class Series
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(name: 'name', type: Types::STRING)]
    private $name;

    #[ORM\OneToMany(mappedBy: 'series', targetEntity: Story::class, cascade: ['persist'])]
    #[ORM\OrderBy(['volume' => 'ASC'])]
    private Collection $stories;

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getStories(): Collection {
        return $this->stories;
    }

    public function addStory(Story $story): Series {
        $story->setSeries($this);
        $this->stories[] = $story;

        return $this;
    }
}
