<?php

namespace App\Service\Reddit;

use App\Denormalizer\CommentWithRepliesDenormalizer;
use App\Denormalizer\CommentDenormalizer;
use App\Denormalizer\CommentsDenormalizer;
use App\Denormalizer\ContentDenormalizer;
use App\Denormalizer\Post\CommentPostDenormalizer;
use App\Denormalizer\PostDenormalizer;
use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\Post;
use App\Repository\CommentRepository;
use App\Repository\ContentRepository;
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
        private readonly ContentRepository $contentRepository,
        private readonly CommentRepository $commentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PostDenormalizer $postDenormalizer,
        private readonly ContentDenormalizer $contentDenormalizer,
        private readonly CommentsDenormalizer $commentsDenormalizer,
        private readonly CommentWithRepliesDenormalizer $commentDenormalizer,
        private readonly CommentDenormalizer $commentNoRepliesDenormalizer,
        private readonly CommentPostDenormalizer $commentPostDenormalizer,
        private readonly Downloader $mediaDownloader,
    ) {
    }

    /**
     * Retrieve a Content from the API hydrated with the response data.
     *
     * @param  string  $type
     * @param  string  $redditId
     *
     * @return Content
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
    public function getContentFromApiByRedditId(string $type, string $redditId): Content
    {
        $response = $this->api->getPostByRedditId($type, $redditId);

        return $this->hydrateContentFromResponseData($type, $response);
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
        if ($type === Kind::KIND_COMMENT && $response['kind'] === 'Listing') {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['children'][0]['data']['link_id']);
        } else if ($type === Kind::KIND_COMMENT && $response['kind'] === Kind::KIND_COMMENT) {
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
     * Instantiate and hydrate a Content Entity based on the provided Response data.
     *
     * Additionally, retrieve the parent Post from the API if the provided
     * Response is of Comment `kind`.
     *
     * @param  string  $type
     * @param  array  $response
     *
     * @return Content
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
    public function hydrateContentFromResponseData(string $type, array $response): Content
    {
        $parentPostResponse = [];

        if ($type === Kind::KIND_COMMENT && $response['kind'] === 'Listing') {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['children'][0]['data']['link_id']);
        } else if ($type === Kind::KIND_COMMENT && $response['kind'] === Kind::KIND_COMMENT) {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['link_id']);
        }

        return $this->contentDenormalizer->denormalize($response, Post::class, null, ['parentPostData' => $parentPostResponse]);
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
        $post = $this->hydrateContentFromResponseData($fullPostResponse['kind'], $fullPostResponse);
        $post = $this->savePost($post);
        $comments = $this->syncCommentsFromApiByPost($post);

        return $post;
    }

    /**
     * Sync a Post and its Comments as presented in the Content's .json URL.
     *
     * Note: Comments are synced as-is. Meaning no `more` Comments are
     * dynamically loaded. To sync all Comments, including `more` loads, use the
     * `syncCommentsFromApiByPost` function.
     *
     * @param  string  $kind
     * @param  string  $postLink
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function syncContentFromJsonUrl(string $kind, string $postLink): Content
    {
        $jsonData = $this->getRawDataFromJsonUrl($postLink);

        if ($kind === Kind::KIND_COMMENT) {
            return $this->persistCommentPostJsonUrlData($jsonData['postData'], $jsonData['commentsData']);
        }

        return $this->persistLinkContentJsonUrlData($jsonData['postData'], $jsonData['commentsData']);
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

    private function getCommentTreeBranch(Content $content, array $postData, array $commentData): \App\Entity\Comment
    {
        // Persist current Comment.
        // $comment = $this->commentNoRepliesDenormalizer->denormalize($content, Post::class, null, ['commentData' => $commentData]);
        // $this->entityManager->persist($comment);

        $comment = $content->getComment();

        // Sync Comment's Parents.
        $this->syncCommentWithParents($content, $comment, $postData, $commentData);

        // Sync Comment's Replies, if any.
        if (isset($commentData['replies']) && !empty($commentData['replies']['data']['children'])) {
            $replies = $this->commentsDenormalizer->denormalize($commentData['replies']['data']['children'], 'array', null, ['post' => $content->getPost(), 'parentComment' => $comment]);

            foreach ($replies as $reply) {
                $comment->addReply($reply);
                $this->entityManager->persist($reply);
            }
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }

    private function syncCommentWithParents(Content $content, \App\Entity\Comment $originalComment, array $postData, array $commentData, ?\App\Entity\Comment $childComment = null): void
    {
        $comment = $this->commentNoRepliesDenormalizer->denormalize($content, Post::class, null, ['commentData' => $commentData]);
        $post = $content->getPost();

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

                $this->syncCommentWithParents($content, $originalComment, $postData, $commentsData[0]['data'], $childComment);
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
     * @return Content
     */
    private function persistLinkContentJsonUrlData(array $postData, array $commentsData): Content
    {
        $content = $this->hydrateContentFromResponseData($postData['kind'], $postData);
        $this->contentRepository->add($content, true);

        $this->processJsonCommentsData($content, $commentsData);

        $this->entityManager->flush();

        return $content;



        $post = $this->hydrateContentFromResponseData($postData['kind'], $postData);
        $this->postRepository->add($post, true);

        $this->processJsonCommentsData($post, $commentsData);

        $this->entityManager->flush();

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
    private function persistCommentPostJsonUrlData(array $postData, array $commentsData): Content
    {
        // MOVE LOGIC TO CONTENTDENORMALIZER, ADD CHECK FOR COMMENT POST, CONTINUE FLOW INTO COMMENTPOSTDENORMALIZER FROM THERE

        $targetComment = $commentsData[0]['data'];
        $content = $this->contentDenormalizer->denormalize($postData, Post::class, null, ['commentData' => $targetComment]);

        // $post = $this->commentPostDenormalizer->denormalize($commentsData[0]['data'], Post::class, null, ['parentPost' => $postData['data']]);

        // @TODO: This is a temporary bandaid solution to the scenario of multiple Comments Saved within the same Post. Requires more investigation for a proper solution.
        // @see: \App\Tests\Feature\JsonUrlSyncTest::testMultpleSavedCommentsFromSamePost()
        // $existingPost = $this->postRepository->findOneBy(['redditPostUrl' => $post->getRedditPostUrl()]);
        // if (!empty($existingPost)) {
        //     return $existingPost;
        // }

        $this->contentRepository->add($content, true);

        $originalComment = $this->getCommentTreeBranch($content, $postData['data'], $targetComment);

        $jsonData = $this->getRawDataFromJsonUrl($content->getPost()->getRedditPostUrl());
        $this->processJsonCommentsData($content, $jsonData['commentsData'], $originalComment);

        $this->entityManager->flush();

        return $content;

        $post = $this->commentPostDenormalizer->denormalize($commentsData[0]['data'], Post::class, null, ['parentPost' => $postData['data']]);

        // @TODO: This is a temporary bandaid solution to the scenario of multiple Comments Saved within the same Post. Requires more investigation for a proper solution.
        // @see: \App\Tests\Feature\JsonUrlSyncTest::testMultpleSavedCommentsFromSamePost()
        $existingPost = $this->postRepository->findOneBy(['redditPostUrl' => $post->getRedditPostUrl()]);
        if (!empty($existingPost)) {
            return $existingPost;
        }

        $this->postRepository->add($post, true);

        $originalComment = $this->getCommentTreeBranch($post, $postData['data'], $commentsData[0]['data']);

        $jsonData = $this->getRawDataFromJsonUrl($post->getRedditPostUrl());
        $this->processJsonCommentsData($post, $jsonData['commentsData'], $originalComment);

        $this->entityManager->flush();

        return $post;
    }

    /**
     * Process and persist the provided JSON Comments data belonging to targeted
     * Content.
     *
     * @param  Content  $content
     * @param  array  $commentsData
     * @param  Comment|null  $originalComment
     *
     * @return void
     */
    private function processJsonCommentsData(Content $content, array $commentsData, \App\Entity\Comment $originalComment = null)
    {
        $rootParentComment = null;
        if (!empty($originalComment)) {
            $rootParentComment = $this->getRootParentCommentFromComment($originalComment);
        }

        $post = $content->getPost();
        foreach ($commentsData as $commentData) {
            if ($commentData['kind'] !== 'more') {
                $comment = $this->commentDenormalizer->denormalize($content, \App\Entity\Comment::class, null, ['commentData' => $commentData['data']]);

                // Do not re-persist the Top Level Comment of the Saved Comment
                // in order to avoid a unique constraint violation.
                if (!empty($rootParentComment) && $rootParentComment->getRedditId() === $comment->getRedditId()) {
                    continue;
                }

                $post->addComment($comment);

                $this->entityManager->persist($comment);
                $this->entityManager->persist($post);
            }
        }
    }

    /**
     * Retrieve the raw JSON data from the provided JSON URL.
     *
     * @param  string  $jsonUrl
     *
     * @return array{
     *      postData: array,
     *      commentsData: array,
     *     }
     * @throws InvalidArgumentException
     */
    private function getRawDataFromJsonUrl(string $jsonUrl): array
    {
        $jsonData = $this->api->getPostFromJsonUrl($jsonUrl);
        if (count($jsonData) !== 2) {
            throw new Exception(sprintf('Unexpected body count for JSON URL: %s', $jsonUrl));
        }

        return [
            'postData' => $jsonData[0]['data']['children'][0],
            'commentsData' => $jsonData[1]['data']['children'],
        ];
    }

    /**
     * Recursively travel up the Comment Tree of the provided Comment and return
     * the root parent Comment.
     *
     * @param  \App\Entity\Comment  $comment
     *
     * @return \App\Entity\Comment
     */
    private function getRootParentCommentFromComment(\App\Entity\Comment $comment): \App\Entity\Comment
    {
        $parentComment = $comment->getParentComment();
        if ($parentComment instanceof \App\Entity\Comment) {
            return $this->getRootParentCommentFromComment($parentComment);
        }

        return $comment;
    }
}
