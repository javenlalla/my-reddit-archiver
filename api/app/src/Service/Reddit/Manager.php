<?php

namespace App\Service\Reddit;

use App\Denormalizer\CommentWithRepliesDenormalizer;
use App\Denormalizer\CommentDenormalizer;
use App\Denormalizer\CommentsDenormalizer;
use App\Denormalizer\Post\CommentPostDenormalizer;
use App\Denormalizer\PostDenormalizer;
use App\Entity\Post;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Service\Reddit\Media\Downloader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class Manager
{
    public function __construct(
        private readonly Api $api,
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PostDenormalizer $postDenormalizer,
        private readonly CommentsDenormalizer $commentsDenormalizer,
        private readonly CommentWithRepliesDenormalizer $commentDenormalizer,
        private readonly CommentDenormalizer $commentNoRepliesDenormalizer,
        private readonly CommentPostDenormalizer $commentPostDenormalizer,
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
     * Convenience function to execute a sync of a Reddit Post by its full
     * Reddit ID.
     *
     * Full Reddit ID example: t3_vepbt0
     *
     * @param  string  $fullRedditId
     *
     * @return Post
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
    public function syncPostByFullRedditId(string $fullRedditId): Post
    {
        $idParts = explode('_', $fullRedditId);
        if (count($idParts) !== 2) {
            throw new Exception(sprintf('Invalid full Reddit ID provided. Expected format t#_abcdef. Received `%s`.', $fullRedditId));
        }

        return $this->syncPostByRedditId($idParts[0], $idParts[1]);
    }

    /**
     * Core function to sync a Reddit Post and persist it to the local database
     * while also downloading any associated media.
     *
     * @param  string  $type
     * @param  string  $redditId
     *
     * @return Post
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
    public function syncPostByRedditId(string $type, string $redditId): Post
    {
        $response = $this->api->getPostByRedditId($type, $redditId);

        $parentPostResponse = [];
        if ($type === Type::TYPE_COMMENT && $response['kind'] === 'Listing') {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['children'][0]['data']['link_id']);
        } else if ($type === Type::TYPE_COMMENT && $response['kind'] === Type::TYPE_COMMENT) {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['link_id']);
        }

        $post = $this->postDenormalizer->denormalize($response, Post::class, null, ['parentPostData' => $parentPostResponse]);

        foreach ($post->getMediaAssets() as $mediaAsset) {
            $this->mediaDownloader->executeDownload($mediaAsset);
        }

        $this->postRepository->add($post, true);

        return $post;
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
     * @throws ExceptionInterface
     */
    public function hydratePostFromResponseData(string $type, array $response): Post
    {
        $parentPostResponse = [];

        if ($type === Type::TYPE_COMMENT && $response['kind'] === 'Listing') {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['children'][0]['data']['link_id']);
        } else if ($type === Type::TYPE_COMMENT && $response['kind'] === Type::TYPE_COMMENT) {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['link_id']);
        }

        return $this->postDenormalizer->denormalize($response, Post::class, null, ['parentPostData' => $parentPostResponse]);
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
     * Sync a Post and its Comments as presented in the Post's .json URL.
     *
     * Note: Comments are synced as-is. Meaning no `more` Comments are
     * dynamically loaded. To sync all Comments, including `more` loads, use the
     * `syncCommentsFromApiByPost` function.
     *
     * @param  string  $kind
     * @param  string  $postLink
     *
     * @return Post
     * @throws InvalidArgumentException
     */
    public function syncPostFromJsonUrl(string $kind, string $postLink): Post
    {
        $jsonData = $this->api->getPostFromJsonUrl($postLink);
        if (count($jsonData) !== 2) {
            throw new Exception(sprintf('Unexpected body count for JSON URL: %s', $postLink));
        }

        $postData = $jsonData[0]['data']['children'][0];
        $commentsData = $jsonData[1]['data']['children'];

        if ($kind === Type::TYPE_COMMENT) {
            return $this->persistCommentPostJsonUrlData($postData, $commentsData);
        }

        return $this->persistLinkPostJsonUrlData($postData, $commentsData);
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

        $comments = $this->commentsDenormalizer->denormalize($commentsRawData, 'array', null, ['post' => $post]);
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

    private function getCommentTreeBranch(Post $post, array $postData, array $commentData)
    {
        // Persist current Comment.
        $comment = $this->commentNoRepliesDenormalizer->denormalize($post, Post::class, null, ['commentData' => $commentData]);
        $this->entityManager->persist($comment);

        // Sync Comment's Parents.
        $this->syncCommentWithParents($post, $comment, $postData, $commentData);

        // Sync Comment's Replies.
        $replies = $this->commentsDenormalizer->denormalize($commentData['replies']['data']['children'], 'array', null, ['post' => $post, 'parentComment' => $comment]);
        foreach ($replies as $reply) {
            $comment->addReply($reply);
            $this->entityManager->persist($reply);
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();
    }

    private function syncCommentWithParents(Post $post, \App\Entity\Comment $originalComment, array $postData, array $commentData, ?\App\Entity\Comment $childComment = null): void
    {
        $comment = $this->commentNoRepliesDenormalizer->denormalize($post, Post::class, null, ['commentData' => $commentData]);

        // Do not re-persist the original Comment.
        if ($originalComment->getRedditId() !== $comment->getRedditId()) {
            if (!empty($childComment)) {
                $comment->addReply($childComment);
                $childComment->setParentComment($comment);
                $this->entityManager->persist($childComment);
            }

            $post->addComment($comment);

            $this->entityManager->persist($comment);
            $this->entityManager->persist($post);
            $this->entityManager->flush();
        }

        // Sync parent Comments, if any.
        if (!empty($commentData['parent_id']) && $this->redditFullIdIsComment($commentData['parent_id'])) {
            $originalPostLink = $postData['permalink'];
            $parentId = str_replace('t1_', '', $commentData['parent_id']);
            $targetCommentLink = $originalPostLink . $parentId;

            $jsonData = $this->api->getPostFromJsonUrl($targetCommentLink);
            if (count($jsonData) !== 2) {
                throw new Exception(sprintf('Unexpected body count for JSON URL: %s', $targetCommentLink));
            }

            $commentsData = $jsonData[1]['data']['children'];

            $childComment = $comment;
            if ($originalComment->getRedditId() === $comment->getRedditId()) {
                $childComment = $originalComment;
            }

                $this->syncCommentWithParents($post, $originalComment, $postData, $commentsData[0]['data'], $childComment);
        }
    }

    /**
     * Verify if the provided full Reddit ID (Ex: t1_ip7pedq) is a Comment ID.
     *
     * @param  string  $id
     *
     * @return bool
     */
    private function redditFullIdIsComment(string $id): bool
    {
        $targetPrefix = 't1_';

        if (str_starts_with($id, $targetPrefix)) {
            return true;
        }

        return false;
    }

    /**
     * Persist the following Post and Comment data for a Link Post as
     * retrieved from the Post's JSON URL.
     *
     * @param  array  $postData
     * @param  array  $commentsData
     *
     * @return Post
     */
    private function persistLinkPostJsonUrlData(array $postData, array $commentsData): Post
    {
        $post = $this->hydratePostFromResponseData($postData['kind'], $postData);
        $this->postRepository->add($post, true);

        foreach ($commentsData as $commentData) {
            if ($commentData['kind'] !== 'more') {
                $comment = $this->commentDenormalizer->denormalize($post, \App\Entity\Comment::class, null, ['commentData' => $commentData['data']]);
                $post->addComment($comment);

                $this->entityManager->persist($comment);
                $this->entityManager->persist($post);
            }
        }

        return $post;
    }

    /**
     * Persist the following Post and Comment data for a Comment Post as
     * retrieved from the Post's JSON URL.
     *
     * @param  array  $postData
     * @param  array  $commentsData
     *
     * @return Post
     */
    private function persistCommentPostJsonUrlData(array $postData, array $commentsData): Post
    {
        $post = $this->commentPostDenormalizer->denormalize($commentsData[0]['data'], Post::class, null, ['parentPost' => $postData['data']]);
        $this->postRepository->add($post, true);

        $this->getCommentTreeBranch($post, $postData['data'], $commentsData[0]['data']);

        // @TODO: Sync other top-level Comments.

        return $post;
    }
}
