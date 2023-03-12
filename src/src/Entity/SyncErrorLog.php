<?php

namespace App\Entity;

use App\Repository\SyncErrorLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SyncErrorLogRepository::class)]
class SyncErrorLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'text', nullable: true)]
    private $url;

    #[ORM\Column(type: 'text', nullable: true)]
    private $contentJson;

    #[ORM\Column(type: 'text', nullable: true)]
    private $error;

    #[ORM\Column(type: 'text', nullable: true)]
    private $errorTrace;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getContentJson(): ?string
    {
        return $this->contentJson;
    }

    public function setContentJson(?string $contentJson): self
    {
        $this->contentJson = $contentJson;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;

        return $this;
    }

    public function getErrorTrace(): ?string
    {
        return $this->errorTrace;
    }

    public function setErrorTrace(?string $errorTrace): self
    {
        $this->errorTrace = $errorTrace;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
