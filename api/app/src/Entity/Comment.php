<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'text')]
    private $text;

    #[ORM\Column(type: 'string', length: 25)]
    private $author;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private $score;

    #[ORM\Column(type: 'string', length: 10)]
    private $redditId;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private $parentCommentId;

    #[ORM\Column(type: 'string', length: 10)]
    private $parentPostId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;

        return $this;
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

    public function getParentCommentId(): ?string
    {
        return $this->parentCommentId;
    }

    public function setParentCommentId(string $parentCommentId): self
    {
        $this->parentCommentId = $parentCommentId;

        return $this;
    }

    public function getParentPostId(): ?string
    {
        return $this->parentPostId;
    }

    public function setParentPostId(string $parentPostId): self
    {
        $this->parentPostId = $parentPostId;

        return $this;
    }
}
