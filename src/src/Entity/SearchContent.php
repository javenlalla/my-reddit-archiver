<?php

namespace App\Entity;

use App\Repository\SearchContentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SearchContentRepository::class)]
class SearchContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contentText = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Subreddit $subreddit = null;

    #[ORM\ManyToOne]
    private ?FlairText $flairText = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Content $content = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContentText(): ?string
    {
        return $this->contentText;
    }

    public function setContentText(string $contentText): static
    {
        $this->contentText = $contentText;

        return $this;
    }

    public function getSubreddit(): ?Subreddit
    {
        return $this->subreddit;
    }

    public function setSubreddit(?Subreddit $subreddit): static
    {
        $this->subreddit = $subreddit;

        return $this;
    }

    public function getFlairText(): ?FlairText
    {
        return $this->flairText;
    }

    public function setFlairText(?FlairText $flairText): static
    {
        $this->flairText = $flairText;

        return $this;
    }

    public function getContent(): ?Content
    {
        return $this->content;
    }

    public function setContent(Content $content): static
    {
        $this->content = $content;

        return $this;
    }
}
