<?php

namespace App\Entity;

use App\Repository\FlairTextRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlairTextRepository::class)]
class FlairText
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $plainText = null;

    #[ORM\Column(length: 255)]
    private ?string $displayText = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlainText(): ?string
    {
        return $this->plainText;
    }

    public function setPlainText(string $plainText): static
    {
        $this->plainText = $plainText;

        return $this;
    }

    public function getDisplayText(): ?string
    {
        return $this->displayText;
    }

    public function setDisplayText(string $displayText): static
    {
        $this->displayText = $displayText;

        return $this;
    }
}
