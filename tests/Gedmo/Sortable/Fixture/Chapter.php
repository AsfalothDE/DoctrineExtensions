<?php

declare(strict_types=1);

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Tests\Sortable\Fixture;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Sortable\Entity\Repository\SortableRepository;

/**
 * @ORM\Entity(repositoryClass="Gedmo\Sortable\Entity\Repository\SortableRepository")
 */
#[ORM\Entity(repositoryClass: SortableRepository::class)]
class Chapter
{
    /**
     * @var int|null
     *
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private $id;

    /**
     * @ORM\Column(name="name", type="string")
     */
    #[ORM\Column(name: 'title', type: Types::STRING)]
    private ?string $title = null;

    /**
     * @ORM\ManyToOne(targetEntity="Story", inversedBy="chapters", cascade={"persist"})
     * @ORM\JoinColumn(name="story_id", referencedColumnName="id", onDelete="CASCADE")
     */
    /**
     * @Gedmo\SortableGroup
     */
    #[ORM\ManyToOne(targetEntity: Story::class, cascade: ['persist'], inversedBy: 'chapters')]
    #[ORM\JoinColumn(name: 'story_id', referencedColumnName: 'id')]
    #[Gedmo\SortableGroup]
    private ?Story $story = null;

    /**
     * @Gedmo\SortablePosition(startWith=1)
     *
     * @ORM\Column(name="number", type="integer")
     */
    #[Gedmo\SortablePosition(startWith: 1)]
    #[ORM\Column(name: 'number', type: Types::INTEGER, nullable: false)]
    private ?int $number = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): void
    {
        $this->number = $number;
    }

    /**
     * Set story
     *
     * @return Chapter
     */
    public function setStory(?Story $story = null)
    {
        $this->story = $story;

        return $this;
    }

    /**
     * Get story
     *
     * @return Story
     */
    public function getStory()
    {
        return $this->story;
    }
}
