<?php

namespace App\Entity;

use App\Repository\ContentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentRepository::class)]
class Content
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: ContentType::class, inversedBy: 'savedContents')]
    #[ORM\JoinColumn(nullable: false)]
    private $contentType;

    #[ORM\OneToOne(inversedBy: 'savedContent', targetEntity: Post::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private $post;

    #[ORM\OneToOne(inversedBy: 'savedContent', targetEntity: Comment::class, cascade: ['persist', 'remove'])]
    private $comment;

    #[ORM\Column(type: 'datetime')]
    private $syncDate;

    #[ORM\ManyToOne(targetEntity: Kind::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $kind;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContentType(): ?ContentType
    {
        return $this->contentType;
    }

    public function setContentType(?ContentType $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(Post $post): self
    {
        $this->post = $post;

        return $this;
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

    public function getSyncDate(): ?\DateTimeInterface
    {
        return $this->syncDate;
    }

    public function setSyncDate(\DateTimeInterface $syncDate): self
    {
        $this->syncDate = $syncDate;

        return $this;
    }

    public function getKind(): ?Kind
    {
        return $this->kind;
    }

    public function setKind(?Kind $kind): self
    {
        $this->kind = $kind;

        return $this;
    }
}
