<?php

namespace App\Entity;

use App\Repository\ThumbnailRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThumbnailRepository::class)]
class Thumbnail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 75)]
    private $filename;

    #[ORM\Column(type: 'string', length: 5)]
    private $dirOne;

    #[ORM\Column(type: 'string', length: 5)]
    private $dirTwo;

    #[ORM\Column(type: 'string', length: 255)]
    private $sourceUrl;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getDirOne(): ?string
    {
        return $this->dirOne;
    }

    public function setDirOne(string $dirOne): self
    {
        $this->dirOne = $dirOne;

        return $this;
    }

    public function getDirTwo(): ?string
    {
        return $this->dirTwo;
    }

    public function setDirTwo(string $dirTwo): self
    {
        $this->dirTwo = $dirTwo;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }
}
