<?php

namespace Sortable\Fixture;

use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Gedmo\Sortable\Entity\Repository\SortableRepository")
 */
class Book
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="items")
     */
    private $category;

    /**
     * @Gedmo\Sortable(groups={"category", "publisher"})
     * @ORM\Column(name="position_by_category", type="integer", nullable=true)
     */
    private $positionByCategory;

    /**
     * @ORM\ManyToOne(targetEntity="Author")
     */
    private $author;

    /**
     * @Gedmo\Sortable(groups={"author", "publisher"})
     * @ORM\Column(name="position_by_author", type="integer", nullable=true)
     */
    private $positionByAuthor;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $publisher;

    /**
     * @ORM\ManyToOne(targetEntity="Series")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $series;

    /**
     * @ORM\Column(name="volume", type="integer", nullable=true)
     * @Gedmo\Sortable(groups={"series"}, startWith=1, sortNullGroup=false)
     */
    private $volume;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param mixed $category
     */
    public function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * @return mixed
     */
    public function getPositionByCategory()
    {
        return $this->positionByCategory;
    }

    /**
     * @param mixed $positionByCategory
     */
    public function setPositionByCategory($positionByCategory)
    {
        $this->positionByCategory = $positionByCategory;
    }

    /**
     * @return mixed
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param mixed $author
     */
    public function setAuthor($author)
    {
        $this->author = $author;
    }

    /**
     * @return mixed
     */
    public function getPositionByAuthor()
    {
        return $this->positionByAuthor;
    }

    /**
     * @param mixed $positionByAuthor
     */
    public function setPositionByAuthor($positionByAuthor)
    {
        $this->positionByAuthor = $positionByAuthor;
    }

    /**
     * @return mixed
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @param mixed $publisher
     */
    public function setPublisher($publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * @return mixed
     */
    public function getSeries()
    {
        return $this->series;
    }

    /**
     * @param mixed $series
     *
     * @return Book
     */
    public function setSeries($series)
    {
        $this->series = $series;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVolume()
    {
        return $this->volume;
    }

    /**
     * @param mixed $volume
     *
     * @return Book
     */
    public function setVolume($volume)
    {
        $this->volume = $volume;
        return $this;
    }
}
