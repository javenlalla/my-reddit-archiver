<?php

namespace App\Entity;

use App\Repository\MediaAssetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaAssetRepository::class)]
class MediaAsset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 40)]
    private $filename;

    #[ORM\Column(type: 'string', length: 5)]
    private $dirOne;

    #[ORM\Column(type: 'string', length: 5)]
    private $dirTwo;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'mediaAssets')]
    #[ORM\JoinColumn(nullable: false)]
    private $parentPost;

    #[ORM\Column(type: 'string', length: 255)]
    private $sourceUrl;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $audioSourceUrl;

    #[ORM\Column(type: 'string', length: 55, nullable: true)]
    private $audioFilename;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $thumbnailSourceUrl;

    #[ORM\Column(type: 'string', length: 75, nullable: true)]
    private $thumbnailFilename;

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

    public function getParentPost(): ?Post
    {
        return $this->parentPost;
    }

    public function setParentPost(?Post $parentPost): self
    {
        $this->parentPost = $parentPost;

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

    public function getAudioSourceUrl(): ?string
    {
        return $this->audioSourceUrl;
    }

    public function setAudioSourceUrl(?string $audioSourceUrl): self
    {
        $this->audioSourceUrl = $audioSourceUrl;

        return $this;
    }

    public function getAudioFilename(): ?string
    {
        return $this->audioFilename;
    }

    public function setAudioFilename(?string $audioFilename): self
    {
        $this->audioFilename = $audioFilename;

        return $this;
    }

    public function getThumbnailSourceUrl(): ?string
    {
        return $this->thumbnailSourceUrl;
    }

    public function setThumbnailSourceUrl(?string $thumbnailSourceUrl): self
    {
        $this->thumbnailSourceUrl = $thumbnailSourceUrl;

        return $this;
    }

    public function getThumbnailFilename(): ?string
    {
        return $this->thumbnailFilename;
    }

    public function setThumbnailFilename(?string $thumbnailFilename): self
    {
        $this->thumbnailFilename = $thumbnailFilename;

        return $this;
    }
}
