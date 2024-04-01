<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'Tag Name must be unique.')]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank]
    private $name;

    #[ORM\ManyToMany(targetEntity: Content::class, mappedBy: 'tags')]
    private $contents;

    #[ORM\Column(length: 6)]
    private ?string $labelColor = null;

    #[ORM\Column(length: 6)]
    private ?string $labelFontColor = null;

    public function __construct()
    {
        $this->contents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Content>
     */
    public function getContents(): Collection
    {
        return $this->contents;
    }

    public function addContent(Content $content): self
    {
        if (!$this->contents->contains($content)) {
            $this->contents[] = $content;
            $content->addTag($this);
        }

        return $this;
    }

    public function removeContent(Content $content): self
    {
        if ($this->contents->removeElement($content)) {
            $content->removeTag($this);
        }

        return $this;
    }

    public function getLabelColor(): ?string
    {
        return $this->labelColor;
    }

    public function setLabelColor(string $labelColor): static
    {
        $this->labelColor = $labelColor;

        return $this;
    }

    public function getLabelFontColor(): ?string
    {
        return $this->labelFontColor;
    }

    public function setLabelFontColor(string $labelFontColor): static
    {
        $this->labelFontColor = $labelFontColor;

        return $this;
    }
}
