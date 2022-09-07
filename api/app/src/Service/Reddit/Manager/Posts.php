<?php

namespace App\Service\Reddit\Manager;

use App\Entity\Post;
use App\Repository\PostRepository;

class Posts
{
    const DEFAULT_LIMIT = 10;

    public function __construct(private readonly PostRepository $postRepository)
    {
    }

    /**
     * @param  int  $limit
     *
     * @return Post[]
     */
    public function getPosts(int $limit = self::DEFAULT_LIMIT)
    {
        $posts = $this->postRepository->findAll();

        return $posts;
    }
}