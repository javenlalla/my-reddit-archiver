<?php

namespace App\Entity;

use App\Repository\CommentAwardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentAwardRepository::class)]
class CommentAward
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Comment::class, inversedBy: 'commentAwards')]
    #[ORM\JoinColumn(nullable: false)]
    private $comment;

    #[ORM\ManyToOne(targetEntity: Award::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $award;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private $count;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAward(): ?Award
    {
        return $this->award;
    }

    public function setAward(?Award $award): self
    {
        $this->award = $award;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }
}
