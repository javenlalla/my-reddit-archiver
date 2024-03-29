<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\AssetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssetRepository::class)]
class Asset
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

    #[ORM\Column(type: 'text')]
    private $sourceUrl;

    #[ORM\Column(type: 'string', length: 75, nullable: true)]
    private $audioFilename;

    #[ORM\Column(type: 'text', nullable: true)]
    private $audioSourceUrl;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'mediaAssets')]
    private $post;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private $isDownloaded;

    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: AssetErrorLog::class, orphanRemoval: true)]
    private $errors;

    public function __construct()
    {
        $this->errors = new ArrayCollection();
    }

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

    public function getAudioFilename(): ?string
    {
        return $this->audioFilename;
    }

    public function setAudioFilename(?string $audioFilename): self
    {
        $this->audioFilename = $audioFilename;

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

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->post = $post;

        return $this;
    }

    public function isDownloaded(): ?bool
    {
        return $this->isDownloaded;
    }

    public function setIsDownloaded(bool $isDownloaded): self
    {
        $this->isDownloaded = $isDownloaded;

        return $this;
    }

    /**
     * @return Collection<int, AssetErrorLog>
     */
    public function getErrors(): Collection
    {
        return $this->errors;
    }

    public function addError(AssetErrorLog $error): self
    {
        if (!$this->errors->contains($error)) {
            $this->errors[] = $error;
            $error->setAsset($this);
        }

        return $this;
    }

    public function removeError(AssetErrorLog $error): self
    {
        if ($this->errors->removeElement($error)) {
            // set the owning side to null (unless already changed)
            if ($error->getAsset() === $this) {
                $error->setAsset(null);
            }
        }

        return $this;
    }
}
