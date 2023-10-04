<?php

namespace App\Entity;

use App\Repository\FlairTextRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlairTextRepository::class)]
class FlairText
{
    const POST_FLAIR_TEXT_KEY = 'link_flair_text';

    const COMMENT_FLAIR_TEXT_KEY = 'author_flair_text';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $plainText = null;

    #[ORM\Column(length: 255)]
    private ?string $displayText = null;

    #[ORM\OneToMany(mappedBy: 'flairText', targetEntity: Post::class)]
    private Collection $posts;

    #[ORM\Column(length: 10)]
    private ?string $referenceId = null;

    #[ORM\OneToMany(mappedBy: 'flairText', targetEntity: Comment::class)]
    private Collection $comments;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlainText(): ?string
    {
        return $this->plainText;
    }

    public function setPlainText(string $plainText): static
    {
        $this->plainText = $plainText;

        return $this;
    }

    public function getDisplayText(): ?string
    {
        return $this->displayText;
    }

    public function setDisplayText(string $displayText): static
    {
        $this->displayText = $displayText;

        return $this;
    }

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setFlairText($this);
        }

        return $this;
    }

    public function removePost(Post $post): static
    {
        if ($this->posts->removeElement($post)) {
            // set the owning side to null (unless already changed)
            if ($post->getFlairText() === $this) {
                $post->setFlairText(null);
            }
        }

        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(string $referenceId): static
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setFlairText($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getFlairText() === $this) {
                $comment->setFlairText(null);
            }
        }

        return $this;
    }
}
