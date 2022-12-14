<?php

namespace App\Entity;

use App\Repository\PostAwardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostAwardRepository::class)]
class PostAward
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'postAwards')]
    #[ORM\JoinColumn(nullable: false)]
    private $post;

    #[ORM\ManyToOne(targetEntity: Award::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $award;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private $count;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->post = $post;

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
