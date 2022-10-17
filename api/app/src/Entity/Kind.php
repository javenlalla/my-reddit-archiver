<?php

namespace App\Entity;

use App\Repository\KindRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KindRepository::class)]
class Kind
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 2)]
    private $redditKindId;

    #[ORM\Column(type: 'string', length: 20)]
    private $name;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRedditKindId(): ?string
    {
        return $this->redditKindId;
    }

    public function setRedditKindId(string $redditKindId): self
    {
        $this->redditKindId = $redditKindId;

        return $this;
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
}
