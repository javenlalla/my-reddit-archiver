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
    private $score = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $url;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\Column(type: 'string', length: 25)]
    private $author;

    #[ORM\Column(type: 'string', length: 25)]
    private $subreddit;

    #[ORM\OneToMany(mappedBy: 'parentPost', targetEntity: Comment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $comments;

    #[ORM\OneToMany(mappedBy: 'parentPost', targetEntity: MediaAsset::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $mediaAssets;

    #[ORM\Column(type: 'string', length: 255)]
    private $redditPostUrl;

    #[ORM\OneToOne(mappedBy: 'post', targetEntity: Content::class, cascade: ['persist', 'remove'])]
    private $content;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    private $type;

    #[ORM\OneToMany(mappedBy: 'post', targetEntity: PostAuthorText::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $postAuthorTexts;

    #[ORM\OneToMany(mappedBy: 'post', targetEntity: PostAward::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $postAwards;

    #[ORM\OneToOne(targetEntity: Thumbnail::class, cascade: ['persist', 'remove'])]
    private $thumbnail;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private $isArchived = false;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->mediaAssets = new ArrayCollection();
        $this->postAuthorTexts = new ArrayCollection();
        $this->postAwards = new ArrayCollection();
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

    public function getContent(): ?Content
    {
        return $this->content;
    }

    public function setContent(Content $content): self
    {
        // set the owning side of the relation if necessary
        if ($content->getPost() !== $this) {
            $content->setPost($this);
        }

        $this->content = $content;

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

    /**
     * @return Collection<int, PostAuthorText>
     */
    public function getPostAuthorTexts(): Collection
    {
        return $this->postAuthorTexts;
    }

    public function addPostAuthorText(PostAuthorText $postAuthorText): self
    {
        if (!$this->postAuthorTexts->contains($postAuthorText)) {
            $this->postAuthorTexts[] = $postAuthorText;
            $postAuthorText->setPost($this);
        }

        return $this;
    }

    public function removePostAuthorText(PostAuthorText $postAuthorText): self
    {
        if ($this->postAuthorTexts->removeElement($postAuthorText)) {
            // set the owning side to null (unless already changed)
            if ($postAuthorText->getPost() === $this) {
                $postAuthorText->setPost(null);
            }
        }

        return $this;
    }

    /**
     * Retrieve the latest/current revision of this Post's Post Author
     * Text entity.
     *
     * @return PostAuthorText|null
     */
    public function getLatestPostAuthorText(): ?PostAuthorText
    {
        $latestPostAuthorText = $this->getPostAuthorTexts()
            ->matching(PostRepository::createLatestPostAuthorTextCriteria())
            ->first()
        ;

        if ($latestPostAuthorText instanceof PostAuthorText) {
            return $latestPostAuthorText;
        }

        return null;
    }

    /**
     * @return Collection<int, PostAward>
     */
    public function getPostAwards(): Collection
    {
        return $this->postAwards;
    }

    public function addPostAward(PostAward $postAward): self
    {
        if (!$this->postAwards->contains($postAward)) {
            $this->postAwards[] = $postAward;
            $postAward->setPost($this);
        }

        return $this;
    }

    public function removePostAward(PostAward $postAward): self
    {
        if ($this->postAwards->removeElement($postAward)) {
            // set the owning side to null (unless already changed)
            if ($postAward->getPost() === $this) {
                $postAward->setPost(null);
            }
        }

        return $this;
    }

    /**
     * Loop through this Post's Post Awards and sum together the counts for
     * each Award, if any, and return the total.
     *
     * @return int
     */
    public function getPostAwardsTrueCount(): int
    {
        $count = 0;
        foreach ($this->getPostAwards() as $postAward) {
            $count += $postAward->getCount();
        }

        return $count;
    }

    public function getThumbnail(): ?Thumbnail
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?Thumbnail $thumbnail): self
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function isIsArchived(): ?bool
    {
        return $this->isArchived;
    }

    public function setIsArchived(bool $isArchived): self
    {
        $this->isArchived = $isArchived;

        return $this;
    }
}
