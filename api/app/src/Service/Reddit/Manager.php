<?php

namespace App\Service\Reddit;

use App\Entity\Post;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Service\Reddit\Hydrator\Comment as CommentHydrator;
use App\Service\Reddit\Media\Downloader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Psr\Cache\InvalidArgumentException;

class Manager
{
    public function __construct(
        private readonly Api $api,
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Hydrator $hydrator,
        private readonly CommentHydrator $commentHydrator,
        private readonly Downloader $mediaDownloader,
    ) {
    }

    /**
     * Retrieve a Post from the API hydrated with the response data.
     *
     * @param  string  $type
     * @param  string  $redditId
     *
     * @return Post
     * @throws InvalidArgumentException
     */
    public function getPostFromApiByRedditId(string $type, string $redditId): Post
    {
        $response = $this->api->getPostByRedditId($type, $redditId);

        return $this->hydratePostFromResponseData($type, $response);
    }

    /**
     * Instantiate and hydrate Post Entity based on the provided Response data.
     *
     * Additionally, retrieve the parent Post from the API if the provided
     * Response is of type Comment.
     *
     * @param  string  $type
     * @param  array  $response
     *
     * @return Post
     * @throws InvalidArgumentException
     */
    public function hydratePostFromResponseData(string $type, array $response): Post
    {
        $parentPostResponse = [];

        if ($type === Type::TYPE_COMMENT && $response['kind'] === 'Listing') {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['children'][0]['data']['link_id']);
        } else if ($type === Type::TYPE_COMMENT && $response['kind'] === Type::TYPE_COMMENT) {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['link_id']);
        }

        return $this->hydrator->hydratePostFromResponse($response, $parentPostResponse);
    }

    public function getPostByRedditId(string $redditId): ?Post
    {
        return $this->postRepository->findOneBy(['redditId' => $redditId]);
    }

    /**
     * Persist the following Post Entity to the database and download any media
     * that may be associated to the post.
     *
     * @param  Post  $post
     *
     * @return Post
     * @throws Exception
     */
    public function savePost(Post $post): Post
    {
        $existingPost = $this->getPostByRedditId($post->getRedditId());

        if ($existingPost instanceof Post) {
            return $existingPost;
        }

        $this->postRepository->save($post);
        foreach ($post->getMediaAssets() as $mediaAsset) {
            $this->mediaDownloader->executeDownload($mediaAsset);
        }

        return $this->postRepository->find($post->getId());
    }

    /**
     * Wrapper function to execute a complete sync of a Post, its media, and
     * its Comments down to local.
     *
     * @param  array  $fullPostResponse
     *
     * @return Post
     * @throws InvalidArgumentException
     */
    public function syncPost(array $fullPostResponse): Post
    {
        $post = $this->hydratePostFromResponseData($fullPostResponse['kind'], $fullPostResponse);
        $post = $this->savePost($post);
        $comments = $this->syncCommentsFromApiByPost($post);

        return $post;
    }

    /**
     * Retrieve all Comments for the provided Post from the API and persist them
     * locally to the database.
     *
     * @param  Post  $post
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function syncCommentsFromApiByPost(Post $post): array
    {
        $commentsRawResponse = $this->api->getPostCommentsByRedditId($post->getRedditPostId());
        $commentsRawData = $commentsRawResponse[1]['data']['children'];

        $comments = $this->commentHydrator->hydrateComments($post, $commentsRawData);
        foreach ($comments as $comment) {
            $this->entityManager->persist($comment);

            // It is intentional that the post-to-comment relation here is
            // only explicitly established for the top-level comments, not
            // replies.
            $post->addComment($comment);
            $this->entityManager->persist($post);
        }

        $this->entityManager->flush();

        return $comments;
    }

    /**
     * Get the count of all Comments, including Replies, attached to the provided
     * Post.
     *
     * @param  Post  $post
     *
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getAllCommentsCountFromPost(Post $post): int
    {
        return $this->commentRepository->getTotalPostCount($post);
    }

    /**
     * Retrieve a Comment locally by its Reddit ID.
     *
     * @param  string  $redditId
     *
     * @return \App\Entity\Comment|null
     */
    public function getCommentByRedditId(string $redditId): ?\App\Entity\Comment
    {
        return $this->commentRepository->findOneBy(['redditId' => $redditId]);
    }
}
