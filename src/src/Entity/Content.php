<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentRepository::class)]
class Content
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Post::class, cascade: ['persist', 'remove'], inversedBy: 'content')]
    #[ORM\JoinColumn(nullable: false)]
    private $post;

    #[ORM\OneToOne(inversedBy: 'content', targetEntity: Comment::class, cascade: ['persist', 'remove'])]
    private $comment;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $nextSyncDate;

    #[ORM\ManyToOne(targetEntity: Kind::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $kind;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'contents')]
    private $tags;

    #[ORM\Column(type: 'string', length: 15)]
    private $fullRedditId;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNextSyncDate(): ?\DateTimeInterface
    {
        return $this->nextSyncDate;
    }

    public function setNextSyncDate(?\DateTimeInterface $nextSyncDate): self
    {
        $this->nextSyncDate = $nextSyncDate;

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

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * Loop through the Content's Tags and verify if the provided Tag is
     * associated to the Content, by its name.
     *
     * @param  string  $tagName
     *
     * @return bool
     */
    public function hasTagName(string $tagName): bool
    {
        foreach ($this->getTags() as $tag) {
            if ($tag->getName() === $tagName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if this Content is a Comment Content.
     *
     * @return bool
     */
    public function isCommentContent(): bool
    {
        if ($this->getComment() instanceof Comment) {
            return true;
        }

        return false;
    }

    public function getFullRedditId(): ?string
    {
        return $this->fullRedditId;
    }

    public function setFullRedditId(string $fullRedditId): self
    {
        $this->fullRedditId = $fullRedditId;

        return $this;
    }
}
