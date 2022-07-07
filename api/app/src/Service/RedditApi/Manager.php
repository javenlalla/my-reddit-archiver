<?php

namespace App\Service\RedditApi;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Service\RedditApi;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Manager
{
    public function __construct(
        private readonly RedditApi $redditApi,
        private readonly PostRepository $postRepository,
        private readonly Hydrator $hydrator
    ) {
    }

    /**
     * Retrieve a Post from the API hydrated with the response data.
     *
     * @param  string  $type
     * @param  string  $redditId
     *
     * @return Post
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getPostFromApiByRedditId(string $type, string $redditId): Post
    {
        $response = $this->redditApi->getPostByRedditId($type, $redditId);

        return $this->hydrator->hydratePostFromResponse($response);
    }

    public function getPostByRedditId(string $redditId): ?Post
    {
        return $this->postRepository->findOneBy(['redditId' => $redditId]);
    }

    public function savePost(\App\Service\RedditApi\Post $post)
    {
        $entityPost = $this->getPostByRedditId($post->getRedditId());

        if (empty($entityPost)) {
            $entityPost = new Post();
        }

        $entityPost->setRedditId($post->getRedditId());
        $entityPost->setTitle($post->getTitle());
        $entityPost->setScore($post->getScore());
        $entityPost->setUrl($post->getUrl());

        $this->postRepository->save($entityPost);
    }

    public function saveComments(){}
}