<?php

namespace App\Entity;

use App\Repository\AuthorTextRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorTextRepository::class)]
class AuthorText
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'text')]
    private $text;

    #[ORM\Column(type: 'text')]
    private $textRawHtml;

    #[ORM\Column(type: 'text')]
    private $textHtml;

    #[ORM\OneToOne(mappedBy: 'authorText', targetEntity: PostAuthorText::class, cascade: ['persist', 'remove'])]
    private $postAuthorText;

    #[ORM\OneToOne(mappedBy: 'authorText', targetEntity: CommentAuthorText::class, cascade: ['persist', 'remove'])]
    private $commentAuthorText;

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

    public function getTextRawHtml(): ?string
    {
        return $this->textRawHtml;
    }

    public function setTextRawHtml(string $textRawHtml): self
    {
        $this->textRawHtml = $textRawHtml;

        return $this;
    }

    public function getTextHtml(): ?string
    {
        return $this->textHtml;
    }

    public function setTextHtml(string $textHtml): self
    {
        $this->textHtml = $textHtml;

        return $this;
    }

    public function getPostAuthorText(): ?PostAuthorText
    {
        return $this->postAuthorText;
    }

    public function setPostAuthorText(PostAuthorText $postAuthorText): self
    {
        // set the owning side of the relation if necessary
        if ($postAuthorText->getAuthorText() !== $this) {
            $postAuthorText->setAuthorText($this);
        }

        $this->postAuthorText = $postAuthorText;

        return $this;
    }

    public function getCommentAuthorText(): ?CommentAuthorText
    {
        return $this->commentAuthorText;
    }

    public function setCommentAuthorText(CommentAuthorText $commentAuthorText): self
    {
        // set the owning side of the relation if necessary
        if ($commentAuthorText->getAuthorText() !== $this) {
            $commentAuthorText->setAuthorText($this);
        }

        $this->commentAuthorText = $commentAuthorText;

        return $this;
    }
}
