<?php

namespace App\Entity;

use App\Repository\ContentTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentTypeRepository::class)]
class ContentType
{
    const CONTENT_TYPE_IMAGE = 'image';

    const CONTENT_TYPE_GIF = 'gif';

    const CONTENT_TYPE_VIDEO = 'video';

    const CONTENT_TYPE_TEXT = 'text';

    const CONTENT_TYPE_IMAGE_GALLERY = 'image_gallery';

    const CONTENT_TYPE_EXTERNAL_LINK = 'external_link';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 20)]
    private $name;

    #[ORM\Column(type: 'string', length: 20)]
    private $displayName;

    #[ORM\OneToMany(mappedBy: 'contentType', targetEntity: Content::class)]
    private $savedContents;

    public function __construct()
    {
        $this->savedContents = new ArrayCollection();
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

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * @return Collection<int, Content>
     */
    public function getSavedContents(): Collection
    {
        return $this->savedContents;
    }

    public function addSavedContent(Content $savedContent): self
    {
        if (!$this->savedContents->contains($savedContent)) {
            $this->savedContents[] = $savedContent;
            $savedContent->setContentType($this);
        }

        return $this;
    }

    public function removeSavedContent(Content $savedContent): self
    {
        if ($this->savedContents->removeElement($savedContent)) {
            // set the owning side to null (unless already changed)
            if ($savedContent->getContentType() === $this) {
                $savedContent->setContentType(null);
            }
        }

        return $this;
    }
}
