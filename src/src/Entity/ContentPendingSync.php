<?php

namespace App\Entity;

use App\Repository\ContentPendingSyncRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentPendingSyncRepository::class)]
class ContentPendingSync
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: ProfileContentGroup::class, inversedBy: 'contentsPendingSync')]
    #[ORM\JoinColumn(nullable: false)]
    private $profileContentGroup;

    #[ORM\Column(type: 'text')]
    private $jsonData;

    #[ORM\Column(type: 'string', length: 15)]
    private $fullRedditId;

    #[ORM\Column(type: 'text', nullable: true)]
    private $parentJsonData;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfileContentGroup(): ?ProfileContentGroup
    {
        return $this->profileContentGroup;
    }

    public function setProfileContentGroup(?ProfileContentGroup $profileContentGroup): self
    {
        $this->profileContentGroup = $profileContentGroup;

        return $this;
    }

    public function getJsonData(): ?string
    {
        return $this->jsonData;
    }

    public function setJsonData(string $jsonData): self
    {
        $this->jsonData = $jsonData;

        return $this;
    }

    public function getFullRedditId(): ?string
    {
        return $this->fullRedditId;
    }

    public function setFullRedditId(string $fullRedditId): self
    {
        $this->fullRedditId = $fullRedditId;

        return $this;
    }

    public function getParentJsonData(): ?string
    {
        return $this->parentJsonData;
    }

    public function setParentJsonData(?string $parentJsonData): self
    {
        $this->parentJsonData = $parentJsonData;

        return $this;
    }
}
