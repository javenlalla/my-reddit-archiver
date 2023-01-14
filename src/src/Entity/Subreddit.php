<?php

namespace App\Entity;

use App\Repository\SubredditRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubredditRepository::class)]
class Subreddit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 15)]
    private $redditId;

    #[ORM\Column(type: 'string', length: 50)]
    private $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(type: 'text', nullable: true)]
    private $descriptionRawHtml;

    #[ORM\Column(type: 'text', nullable: true)]
    private $descriptionHtml;

    #[ORM\Column(type: 'text', nullable: true)]
    private $publicDescription;

    #[ORM\Column(type: 'text', nullable: true)]
    private $publicDescriptionRawHtml;

    #[ORM\Column(type: 'text', nullable: true)]
    private $publicDescriptionHtml;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\OneToMany(mappedBy: 'subreddit', targetEntity: Post::class, orphanRemoval: true)]
    private $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescriptionRawHtml(): ?string
    {
        return $this->descriptionRawHtml;
    }

    public function setDescriptionRawHtml(?string $descriptionRawHtml): self
    {
        $this->descriptionRawHtml = $descriptionRawHtml;

        return $this;
    }

    public function getDescriptionHtml(): ?string
    {
        return $this->descriptionHtml;
    }

    public function setDescriptionHtml(?string $descriptionHtml): self
    {
        $this->descriptionHtml = $descriptionHtml;

        return $this;
    }

    public function getPublicDescription(): ?string
    {
        return $this->publicDescription;
    }

    public function setPublicDescription(?string $publicDescription): self
    {
        $this->publicDescription = $publicDescription;

        return $this;
    }

    public function getPublicDescriptionRawHtml(): ?string
    {
        return $this->publicDescriptionRawHtml;
    }

    public function setPublicDescriptionRawHtml(?string $publicDescriptionRawHtml): self
    {
        $this->publicDescriptionRawHtml = $publicDescriptionRawHtml;

        return $this;
    }

    public function getPublicDescriptionHtml(): ?string
    {
        return $this->publicDescriptionHtml;
    }

    public function setPublicDescriptionHtml(?string $publicDescriptionHtml): self
    {
        $this->publicDescriptionHtml = $publicDescriptionHtml;

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

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts[] = $post;
            $post->setSubreddit($this);
        }

        return $this;
    }

    public function removePost(Post $post): self
    {
        if ($this->posts->removeElement($post)) {
            // set the owning side to null (unless already changed)
            if ($post->getSubreddit() === $this) {
                $post->setSubreddit(null);
            }
        }

        return $this;
    }
}
