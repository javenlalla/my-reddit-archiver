<?php

namespace App\Service\Reddit;

use App\Denormalizer\CommentWithRepliesDenormalizer;
use App\Denormalizer\CommentDenormalizer;
use App\Denormalizer\CommentsAndMoreDenormalizer;
use App\Denormalizer\ContentDenormalizer;
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

class Manager
{
    /**
     * Regex pattern to detect if a given URL or URI is a direct Reddit Comment
     * link such as:
     *  - https://www.reddit.com/r/golang/comments/z2ngmf/comment/ixhzp48/
     *  - /r/science/comments/wf1e8p/exercising_almost_daily_for_up_to_an_hour_at_a/iirwrq4/
     *
     * It is meant to NOT match a Link Post URL such as:
     * https://www.reddit.com/r/golang/comments/z2ngmf/
     */
    public const COMMENT_URL_REGEX_PATTERN = '/comments\/[a-zA-Z0-9]{4,10}\/.*\/[a-zA-Z0-9]{4,10}/i';

    public function __construct(
        private readonly Api $api,
        private readonly PostRepository $postRepository,
        private readonly ContentRepository $contentRepository,
        private readonly CommentRepository $commentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentDenormalizer $contentDenormalizer,
        private readonly CommentsAndMoreDenormalizer $commentsAndMoreDenormalizer,
        private readonly CommentWithRepliesDenormalizer $commentDenormalizer,
        private readonly CommentDenormalizer $commentNoRepliesDenormalizer,
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
     * @throws InvalidArgumentException
     */
    public function getContentFromApiByRedditId(string $type, string $redditId): Content
    {
        $response = $this->api->getPostByRedditId($type, $redditId);

        return $this->hydrateContentFromResponseData($type, $response);
    }

    /**
     * Convenience function to execute a sync of a Reddit Content by its full
     * Reddit ID.
     *
     * Full Reddit ID example: t3_vepbt0
     *
     * @param  string  $fullRedditId
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function syncContentFromApiByFullRedditId(string $fullRedditId): Content
    {
        $idParts = explode('_', $fullRedditId);
        if (count($idParts) !== 2) {
            throw new Exception(sprintf('Invalid full Reddit ID provided. Expected format t#_abcdef. Received `%s`.', $fullRedditId));
        }

        return $this->syncContentFromApiByRedditId($idParts[0], $idParts[1]);
    }

    /**
     * Core function to sync a Reddit Content directly from the Reddit API and
     * persist it to the local database while also downloading any associated
     * media.
     *
     * @param  string  $kind
     * @param  string  $redditId
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function syncContentFromApiByRedditId(string $kind, string $redditId): Content
    {
        $response = $this->api->getPostByRedditId($kind, $redditId);
        $contentUrl = $response['data']['children'][0]['data']['permalink'];

        return $this->syncContentByUrl($contentUrl);
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
        // foreach ($post->getMediaAssets() as $mediaAsset) {
        //     $this->mediaDownloader->downloadMediaAsset($mediaAsset);
        // }

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
        $content = $this->hydrateContentFromResponseData($fullPostResponse['kind'], $fullPostResponse);
        $this->contentRepository->add($content, true);

        $post = $this->savePost($content->getPost());
        $comments = $this->syncCommentsFromApiByPost($post);

        return $post;
    }

    /**
     * Sync a piece of Content by its URL.
     * @param  string  $url
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function syncContentByUrl(string $url): Content
    {
        $kind = Kind::KIND_LINK;

        $isCommentUrl = preg_match(self::COMMENT_URL_REGEX_PATTERN, $url);
        if ($isCommentUrl === 1) {
            $kind = Kind::KIND_COMMENT;
        }

        return $this->syncContentFromJsonUrl($kind, $url);
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
        $commentsRawResponse = $this->api->getPostCommentsByRedditId($post->getRedditId());
        $commentsRawData = $commentsRawResponse[1]['data']['children'];

        $comments = $this->commentsAndMoreDenormalizer->denormalize($commentsRawData, 'array', null, ['post' => $post]);
        foreach ($comments as $comment) {
            $existingComment = $this->commentRepository->findOneBy(['redditId' => $comment->getRedditId()]);

            if (empty($existingComment)) {
                $this->entityManager->persist($comment);

                $post->addComment($comment);
                $this->entityManager->persist($post);
            }
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
     * @return Comment|null
     */
    public function getCommentByRedditId(string $redditId): ?Comment
    {
        return $this->commentRepository->findOneBy(['redditId' => $redditId]);
    }

    private function syncCommentTreeBranch(Content $content, array $postData, array $commentData): Comment
    {
        $comment = $content->getComment();

        // Sync Comment's Parents.
        $this->syncCommentWithParents($content, $comment, $postData, $commentData);

        // Sync Comment's Replies, if any.
        if (isset($commentData['replies']) && !empty($commentData['replies']['data']['children'])) {
            $replies = $this->commentsAndMoreDenormalizer->denormalize($commentData['replies']['data']['children'], 'array', null, ['post' => $content->getPost(), 'parentComment' => $comment]);

            foreach ($replies as $reply) {
                $existingComment = $this->commentRepository->findOneBy(['redditId' => $reply->getRedditId()]);

                if (empty($existingComment)) {
                    $comment->addReply($reply);
                    $this->entityManager->persist($reply);
                }
            }
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }

    private function syncCommentWithParents(Content $content, Comment $originalComment, array $postData, array $commentData, ?Comment $childComment = null): void
    {
        $post = $content->getPost();
        $comment = $this->commentNoRepliesDenormalizer->denormalize($post, Post::class, null, ['commentData' => $commentData]);

        $existingComment = $this->commentRepository->findOneBy(['redditId' => $comment->getRedditId()]);
        if (!empty($existingComment)) {
            $comment = $existingComment;
        }

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
        $content = $this->executePreAddContentHooks($content);

        $this->contentRepository->add($content, true);

        $this->processJsonCommentsData($content, $commentsData);

        $this->entityManager->flush();

        return $content;
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
        $targetComment = $commentsData[0]['data'];
        $content = $this->contentDenormalizer->denormalize($postData, Post::class, null, ['commentData' => $targetComment]);
        $content = $this->executePreAddContentHooks($content);

        $existingContent = $this->contentRepository->findOneBy(['comment' => $content->getComment()]);
        if ($existingContent instanceof Content) {
            $content = $existingContent;
        } else {
            $this->contentRepository->add($content, true);
        }

        $originalComment = $this->syncCommentTreeBranch($content, $postData['data'], $targetComment);
        $jsonData = $this->getRawDataFromJsonUrl($content->getPost()->getRedditPostUrl());
        $this->processJsonCommentsData($content, $jsonData['commentsData'], $originalComment);

        $this->entityManager->flush();

        return $content;
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
    private function processJsonCommentsData(Content $content, array $commentsData, Comment $originalComment = null)
    {
        $rootParentComment = null;
        if (!empty($originalComment)) {
            $rootParentComment = $this->getRootParentCommentFromComment($originalComment);
        }

        $post = $content->getPost();
        foreach ($commentsData as $commentData) {
            if ($commentData['kind'] !== 'more') {
                $comment = $this->commentDenormalizer->denormalize($post, Comment::class, null, ['commentData' => $commentData['data']]);

                $existingComment = $this->commentRepository->findOneBy(['redditId' => $comment->getRedditId()]);
                if (!empty($existingComment)) {
                    $comment = $existingComment;

                    // Existing Comment is already associated to the target
                    // Post. Skip additional processing.
                    if ($comment->getParentPost()->getRedditId() === $post->getRedditId()) {
                        continue;
                    }
                }

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
     * @param  Comment  $comment
     *
     * @return Comment
     */
    private function getRootParentCommentFromComment(Comment $comment): Comment
    {
        $parentComment = $comment->getParentComment();
        if ($parentComment instanceof Comment) {
            return $this->getRootParentCommentFromComment($parentComment);
        }

        return $comment;
    }

    /**
     * Execute any necessary logic before persisting a Content to the database
     * such as downloading related Media Assets.
     *
     * @param  Content  $content
     *
     * @return Content
     * @throws Exception
     */
    private function executePreAddContentHooks(Content $content): Content
    {
        // $post = $content->getPost();
        // foreach ($post->getMediaAssets() as $mediaAsset) {
        //     $this->mediaDownloader->downloadMediaAsset($mediaAsset);
        // }

        // if (!empty($post->getThumbnailAsset())) {
        //     $this->mediaDownloader->downloadThumbnail($post->getThumbnailAsset());
        // }

        return $content;
    }
}
