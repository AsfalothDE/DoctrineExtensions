<?php

declare(strict_types=1);

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Tests\Sortable\Fixture;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Sortable\Entity\Repository\SortableRepository;

/**
 * @ORM\Entity(repositoryClass="Gedmo\Sortable\Entity\Repository\SortableRepository")
 */
#[ORM\Entity(repositoryClass: SortableRepository::class)]
class Story
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
    #[ORM\Column(name: 'name', type: Types::STRING)]
    private ?string $title = null;

    /**
     * @var Collection
     *
     * @ORM\OneToMany(targetEntity="Chapter", mappedBy="story")
     * @ORM\OrderBy({"number" = "ASC"})
     */
    #[ORM\OneToMany(mappedBy: 'story', targetEntity: Chapter::class, cascade: ['persist'])]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private $chapters;

    #[ORM\ManyToOne(targetEntity: Series::class, cascade: ['persist'], inversedBy: 'stories')]
    #[ORM\JoinColumn(name: 'series_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Gedmo\SortableGroup(sortNullValues: false)]
    private ?Series $series = null;

    #[Gedmo\SortablePosition(startWith: 1, incrementBy: 10)]
    #[ORM\Column(name: 'volume', type: Types::INTEGER, nullable: true)]
    private ?int $volume = null;

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

    public function getChapters(): ?Collection
    {
        return $this->chapters;
    }

    /**
     * Add chapter
     */
    public function addChapter(Chapter $chapter)
    {
        $chapter->setStory($this);
        $this->chapters[] = $chapter;

        return $this;
    }

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    /**
     * Remove chapter
     */
    public function removeChapter(Chapter $chapter)
    {
        $this->chapters->removeElement($chapter);
    }

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function setSeries(Series $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function setVolume(?int $volume): self
    {
        $this->volume = $volume;

        return $this;
    }
}
