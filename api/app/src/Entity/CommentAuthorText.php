<?php

namespace App\Entity;

use App\Repository\CommentAuthorTextRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentAuthorTextRepository::class)]
class CommentAuthorText
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Comment::class, inversedBy: 'authorTexts')]
    #[ORM\JoinColumn(nullable: false)]
    private $comment;

    #[ORM\OneToOne(inversedBy: 'commentAuthorText', targetEntity: AuthorText::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private $authorText;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getComment(): ?Comment
    {
        return $this->comment;
    }

    public function setComment(?Comment $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getAuthorText(): ?AuthorText
    {
        return $this->authorText;
    }

    public function setAuthorText(AuthorText $authorText): self
    {
        $this->authorText = $authorText;

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
