<?php

namespace App\Entity;

use App\Repository\ItemJsonRepository;
use Doctrine\ORM\Mapping as ORM;
use Exception;

#[ORM\Entity(repositoryClass: ItemJsonRepository::class)]
class ItemJson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 15)]
    private $redditId;

    #[ORM\Column(type: 'text')]
    private $jsonBody;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRedditId(): ?string
    {
        return $this->redditId;
    }

    public function setRedditId(string $redditId): self
    {
        $this->redditId = $redditId;

        return $this;
    }

    public function getJsonBody(): ?string
    {
        return $this->jsonBody;
    }

    public function setJsonBody(string $jsonBody): self
    {
        $this->jsonBody = $jsonBody;

        return $this;
    }

    public function getJsonBodyArray(): array
    {
        return json_decode($this->getJsonBody(), true);
    }
}
