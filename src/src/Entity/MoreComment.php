<?php

namespace App\Entity;

use App\Repository\MoreCommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MoreCommentRepository::class)]
class MoreComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 10)]
    private $redditId;

    #[ORM\ManyToOne(targetEntity: Comment::class, inversedBy: 'moreComments')]
    private $parentComment;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'moreComments')]
    private $parentPost;

    #[ORM\Column(type: 'text')]
    private $url;

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

    public function getParentComment(): ?Comment
    {
        return $this->parentComment;
    }

    public function setParentComment(?Comment $parentComment): self
    {
        $this->parentComment = $parentComment;

        return $this;
    }

    public function getParentPost(): ?Post
    {
        return $this->parentPost;
    }

    public function setParentPost(?Post $parentPost): self
    {
        $this->parentPost = $parentPost;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }
}
