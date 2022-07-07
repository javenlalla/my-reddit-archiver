<?php

namespace App\Service\RedditApi;

use App\Entity\Post;
use App\Entity\Post as PostEntity;
use App\Repository\PostRepository;
use App\Service\RedditApi;

class Manager
{
    public function __construct(private RedditApi $redditApi, private PostRepository $postRepository)
    {

    }

    public function getPostByRedditId(string $redditId): ?PostEntity
    {
        return $this->postRepository->findOneBy(['redditId' => $redditId]);
    }

    public function savePost(\App\Service\RedditApi\Post $post)
    {
        $entityPost = $this->getPostByRedditId($post->getRedditId());

        if (empty($entityPost)) {
            $entityPost = new PostEntity();
        }

        $entityPost->setRedditId($post->getRedditId());
        $entityPost->setTitle($post->getTitle());
        $entityPost->setScore($post->getScore());
        $entityPost->setUrl($post->getUrl());

        $this->postRepository->save($entityPost);
    }

    public function saveComments(){}
}