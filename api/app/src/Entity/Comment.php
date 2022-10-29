<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 25)]
    private $author;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private $score;

    #[ORM\Column(type: 'string', length: 10, unique: true)]
    private $redditId;

    #[ORM\ManyToOne(targetEntity: self::class, cascade: ['persist', 'remove'], inversedBy: 'replies')]
    private $parentComment;

    #[ORM\OneToMany(mappedBy: 'parentComment', targetEntity: self::class, cascade: ['persist', 'remove'])]
    private $replies;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    private $parentPost;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private $depth;

    #[ORM\OneToOne(mappedBy: 'comment', targetEntity: Content::class, cascade: ['persist', 'remove'])]
    private $content;

    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: CommentAuthorText::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $commentAuthorTexts;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
        $this->commentAuthorTexts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getParentComment(): ?self
    {
        return $this->parentComment;
    }

    public function setParentComment(?self $parentComment): self
    {
        $this->parentComment = $parentComment;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(self $reply): self
    {
        if (!$this->replies->contains($reply)) {
            $this->replies[] = $reply;
            $reply->setParentComment($this);
        }

        return $this;
    }

    public function removeReply(self $reply): self
    {
        if ($this->replies->removeElement($reply)) {
            // set the owning side to null (unless already changed)
            if ($reply->getParentComment() === $this) {
                $reply->setParentComment(null);
            }
        }

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

    public function getDepth(): ?int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): self
    {
        $this->depth = $depth;

        return $this;
    }

    public function getContent(): ?Content
    {
        return $this->content;
    }

    public function setContent(?Content $content): self
    {
        // unset the owning side of the relation if necessary
        if ($content === null && $this->content !== null) {
            $this->content->setComment(null);
        }

        // set the owning side of the relation if necessary
        if ($content !== null && $content->getComment() !== $this) {
            $content->setComment($this);
        }

        $this->content = $content;

        return $this;
    }

    /**
     * @return Collection<int, CommentAuthorText>
     */
    public function getCommentAuthorTexts(): Collection
    {
        return $this->commentAuthorTexts;
    }

    public function addCommentAuthorText(CommentAuthorText $commentAuthorText): self
    {
        if (!$this->commentAuthorTexts->contains($commentAuthorText)) {
            $this->commentAuthorTexts[] = $commentAuthorText;
            $commentAuthorText->setComment($this);
        }

        return $this;
    }

    public function removeCommentAuthorText(CommentAuthorText $commentAuthorText): self
    {
        if ($this->commentAuthorTexts->removeElement($commentAuthorText)) {
            // set the owning side to null (unless already changed)
            if ($commentAuthorText->getComment() === $this) {
                $commentAuthorText->setComment(null);
            }
        }

        return $this;
    }

    /**
     * Retrieve the latest/current revision of this Comment's Comment Author
     * Text entity.
     *
     * @return CommentAuthorText
     */
    public function getLatestCommentAuthorText(): CommentAuthorText
    {
        return $this->getCommentAuthorTexts()
            ->matching(CommentRepository::createLatestCommentAuthorTextCriteria())
            ->first()
        ;
    }
}
