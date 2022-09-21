<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    const FULL_REDDIT_ID_FORMAT = '%s_%s';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 10, unique: true)]
    private $redditId;

    #[ORM\Column(type: 'text')]
    private $title;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private $score;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $url;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $type;

    #[ORM\ManyToOne(targetEntity: ContentType::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $contentType;

    #[ORM\Column(type: 'text', nullable: true)]
    private $authorText;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\Column(type: 'string', length: 25)]
    private $author;

    #[ORM\Column(type: 'string', length: 25)]
    private $subreddit;

    #[ORM\OneToMany(mappedBy: 'parentPost', targetEntity: Comment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $comments;

    #[ORM\Column(type: 'text', nullable: true)]
    private $authorTextHtml;

    #[ORM\Column(type: 'text', nullable: true)]
    private $authorTextRawHtml;

    #[ORM\OneToMany(mappedBy: 'parentPost', targetEntity: MediaAsset::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $mediaAssets;

    #[ORM\Column(type: 'string', length: 255)]
    private $redditPostUrl;

    #[ORM\Column(type: 'string', length: 10)]
    private $redditPostId;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->mediaAssets = new ArrayCollection();
    }

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
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

    public function getAuthorText(): ?string
    {
        return $this->authorText;
    }

    public function setAuthorText(?string $authorText): self
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

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getSubreddit(): ?string
    {
        return $this->subreddit;
    }

    public function setSubreddit(string $subreddit): self
    {
        $this->subreddit = $subreddit;

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * Get only Top Level Comments of this Post.
     * I.E.: retrieve the base Comments that do not contain any Parent Comment
     * so are considered to be at the top level of the Comment tree.
     *
     * @TODO: replace filter logic with Criteria to avoid fetching entire collection prior to filtering: https://stackoverflow.com/a/18584028
     *
     * @return Collection<int, Comment>
     */
    public function getTopLevelComments(): Collection
    {
        return $this->comments->filter(function($comment) {
            return empty($comment->getParentComment());
        });
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments[] = $comment;
            $comment->setParentPost($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getParentPost() === $this) {
                $comment->setParentPost(null);
            }
        }

        return $this;
    }

    public function getAuthorTextHtml(): ?string
    {
        return $this->authorTextHtml;
    }

    public function setAuthorTextHtml(?string $authorTextHtml): self
    {
        $this->authorTextHtml = $authorTextHtml;

        return $this;
    }

    public function getAuthorTextRawHtml(): ?string
    {
        return $this->authorTextRawHtml;
    }

    public function setAuthorTextRawHtml(?string $authorTextRawHtml): self
    {
        $this->authorTextRawHtml = $authorTextRawHtml;

        return $this;
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    public function getMediaAssets(): Collection
    {
        return $this->mediaAssets;
    }

    public function addMediaAsset(MediaAsset $mediaAsset): self
    {
        if (!$this->mediaAssets->contains($mediaAsset)) {
            $this->mediaAssets[] = $mediaAsset;
            $mediaAsset->setParentPost($this);
        }

        return $this;
    }

    public function removeMediaAsset(MediaAsset $mediaAsset): self
    {
        if ($this->mediaAssets->removeElement($mediaAsset)) {
            // set the owning side to null (unless already changed)
            if ($mediaAsset->getParentPost() === $this) {
                $mediaAsset->setParentPost(null);
            }
        }

        return $this;
    }

    public function getRedditPostUrl(): ?string
    {
        return $this->redditPostUrl;
    }

    public function setRedditPostUrl(string $redditPostUrl): self
    {
        $this->redditPostUrl = $redditPostUrl;

        return $this;
    }

    public function getRedditPostId(): ?string
    {
        return $this->redditPostId;
    }

    public function setRedditPostId(string $redditPostId): self
    {
        $this->redditPostId = $redditPostId;

        return $this;
    }
}
