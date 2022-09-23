<?php

namespace App\Service\Reddit;

use App\Denormalizer\CommentWithRepliesDenormalizer;
use App\Denormalizer\CommentDenormalizer;
use App\Denormalizer\CommentPostDenormalizer;
use App\Denormalizer\CommentsDenormalizer;
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

class Manager
{
    public function __construct(
        private readonly Api $api,
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Hydrator $hydrator,
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

        if ($kind === Hydrator::TYPE_COMMENT) {
            $post = $this->commentPostDenormalizer->denormalize($commentsData[0]['data'], Post::class, null, ['parentPost' => $postData['data']]);

            $this->getCommentTreeBranch($post, $postData['data'], $commentsData[0]['data']);

            // @TODO: Sync other top-level Comments.
        } else {
            $post = $this->hydratePostFromResponseData($postData['kind'], $postData);

            foreach ($commentsData as $commentData) {
                if ($commentData['kind'] !== 'more') {
                    $comment = $this->commentDenormalizer->denormalize($post, \App\Entity\Comment::class, null, ['commentData' => $commentData['data']]);
                    $post->addComment($comment);

                    $this->entityManager->persist($comment);
                    $this->entityManager->persist($post);
                }
            }
        }

        $post = $this->savePost($post);

        $this->entityManager->flush();

        return $this->postRepository->find($post->getId());
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
        $this->syncCommentWithParents($post, $postData, $commentData);
        // Persist current Comment.


        // Sync Replies.

        // Track already-persisted Comments/Replies.
    }

    private function syncCommentWithParents(Post $post, array $postData, array $commentData, ?\App\Entity\Comment $childComment = null): void
    {
        $comment = $this->commentNoRepliesDenormalizer->denormalize($post, Post::class, null, ['commentData' => $commentData]);
        if (!empty($childComment)) {
            $comment->addReply($childComment);
            $childComment->setParentComment($comment);
            $this->entityManager->persist($childComment);
        }

        $post->addComment($comment);

        $this->entityManager->persist($comment);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

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

            $this->syncCommentWithParents($post, $postData, $commentsData[0]['data'], $comment);
        }
    }

    private function redditFullIdIsComment(string $id): bool
    {
        $targetPrefix = 't1_';

        if (str_starts_with($id, $targetPrefix)) {
            return true;
        }

        return false;
    }
}
