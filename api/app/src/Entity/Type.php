<?php

namespace App\Entity;

use App\Repository\TypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeRepository::class)]
class Type
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 2)]
    private $redditTypeId;

    #[ORM\Column(type: 'string', length: 20)]
    private $name;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRedditTypeId(): ?string
    {
        return $this->redditTypeId;
    }

    public function setRedditTypeId(string $redditTypeId): self
    {
        $this->redditTypeId = $redditTypeId;

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
