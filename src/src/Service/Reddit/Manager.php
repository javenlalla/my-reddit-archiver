<?php
declare(strict_types=1);

namespace App\Service\Reddit;

use App\Denormalizer\CommentWithRepliesDenormalizer;
use App\Denormalizer\CommentDenormalizer;
use App\Denormalizer\CommentsAndMoreDenormalizer;
use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\Post;
use App\Repository\CommentRepository;
use App\Repository\ContentRepository;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager\Contents;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly Contents $contentsManager,
        private readonly ContentRepository $contentRepository,
        private readonly CommentRepository $commentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CommentsAndMoreDenormalizer $commentsAndMoreDenormalizer,
        private readonly CommentWithRepliesDenormalizer $commentDenormalizer,
        private readonly CommentDenormalizer $commentNoRepliesDenormalizer,
    ) {
    }

    /**
     * Convenience function to execute a sync of a Reddit Content by its full
     * Reddit ID.
     *
     * Full Reddit ID example: t3_vepbt0
     *
     * @param  Context  $context
     * @param  string  $fullRedditId
     * @param  bool  $syncComments
     * @param  bool  $downloadAssets
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function syncContentFromApiByFullRedditId(Context $context, string $fullRedditId, bool $syncComments = false, bool $downloadAssets = false): Content
    {
        $response = $this->api->getRedditItemInfoById($context, $fullRedditId);
        $contentUrl = $response['data']['permalink'];

        return $this->syncContentByUrl($context, $contentUrl, $syncComments, $downloadAssets);
    }

    /**
     * Sync a piece of Content by its URL.
     *
     * @param  Context  $context
     * @param  string  $url
     * @param  bool  $syncComments
     * @param  bool  $downloadAssets
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function syncContentByUrl(Context $context, string $url, bool $syncComments = false, bool $downloadAssets = false): Content
    {
        $kind = Kind::KIND_LINK;

        $isCommentUrl = preg_match(self::COMMENT_URL_REGEX_PATTERN, $url);
        if ($isCommentUrl === 1) {
            $kind = Kind::KIND_COMMENT;
        }

        return $this->syncContentFromJsonUrl($context, $kind, $url, $syncComments, $downloadAssets);
    }

    /**
     * Sync a Post and its Comments as presented in the Content's .json URL.
     *
     * Note: Comments are synced as-is. Meaning no `more` Comments are
     * dynamically loaded. To sync all Comments, including `more` loads, use the
     * `syncCommentsFromApiByPost` function.
     *
     * @param  Context  $context
     * @param  string  $kind
     * @param  string  $postLink
     * @param  bool  $syncComments
     * @param  bool  $downloadAssets
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function syncContentFromJsonUrl(Context $context, string $kind, string $postLink, bool $syncComments = false, bool $downloadAssets = false): Content
    {
        $jsonData = $this->getRawDataFromJsonUrl($context, $postLink);

        if ($kind === Kind::KIND_COMMENT) {
            return $this->persistCommentPostJsonUrlData($context, $jsonData['postData'], $jsonData['commentsData']);
        }

        return $this->persistLinkContentJsonUrlData($jsonData['postData'], $jsonData['commentsData'], $syncComments, $downloadAssets);
    }

    /**
     * Instantiate and hydrate a Content Entity based on the provided Response data.
     *
     * Additionally, retrieve the parent Post from the API if the provided
     * Response is of Comment `kind`.
     *
     * @param  string  $type
     * @param  array  $response
     * @param  bool  $downloadAssets
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function hydrateContentFromResponseData(string $type, array $response, bool $downloadAssets = false): Content
    {
        $parentPostResponse = [];

        if ($type === Kind::KIND_COMMENT && $response['kind'] === 'Listing') {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['children'][0]['data']['link_id']);
        } else if ($type === Kind::KIND_COMMENT && $response['kind'] === Kind::KIND_COMMENT) {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['link_id']);
        }

        return $this->contentsManager->parseAndDenormalizeContent($response, ['parentPostData' => $parentPostResponse, 'downloadAssets' => $downloadAssets]);
    }

    private function syncCommentTreeBranch(Context $context, Content $content, array $postData, array $commentData): Comment
    {
        $comment = $content->getComment();

        // Sync Comment's Parents.
        $this->syncCommentWithParents($context, $content, $comment, $postData, $commentData);

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

    private function syncCommentWithParents(Context $context, Content $content, Comment $originalComment, array $postData, array $commentData, ?Comment $childComment = null): void
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

            $jsonData = $this->api->getPostFromJsonUrl($context, $targetCommentLink);
            if (count($jsonData) !== 2) {
                throw new Exception(sprintf('Unexpected body count for JSON URL: %s', $targetCommentLink));
            }

            $commentsData = $jsonData[1]['data']['children'];

            $childComment = $comment;
            if ($originalComment->getRedditId() === $comment->getRedditId()) {
                $childComment = $originalComment;
            }

            $this->syncCommentWithParents($context, $content, $originalComment, $postData, $commentsData[0]['data'], $childComment);
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
     * @param  bool  $syncComments
     * @param  bool  $downloadAssets
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    private function persistLinkContentJsonUrlData(array $postData, array $commentsData, bool $syncComments = false, bool $downloadAssets = false): Content
    {
        $content = $this->hydrateContentFromResponseData($postData['kind'], $postData, $downloadAssets);

        $this->contentRepository->add($content, true);

        if ($syncComments === true) {
            $this->processJsonCommentsData($content, $commentsData);
        }

        $this->entityManager->flush();

        return $content;
    }

    /**
     * Persist the following Post and Comment data for a Comment Post as
     * retrieved from the Post's JSON URL.
     *
     * @param  Context  $context
     * @param  array  $postData
     * @param  array  $commentsData
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    private function persistCommentPostJsonUrlData(Context $context, array $postData, array $commentsData): Content
    {
        $targetComment = $commentsData[0]['data'];
        $content = $this->contentsManager->parseAndDenormalizeContent($postData, ['commentData' => $targetComment]);

        $existingContent = $this->contentRepository->findOneBy(['comment' => $content->getComment()]);
        if ($existingContent instanceof Content) {
            $content = $existingContent;
        } else {
            $this->contentRepository->add($content, true);
        }

        $originalComment = $this->syncCommentTreeBranch($context, $content, $postData['data'], $targetComment);
        $jsonData = $this->getRawDataFromJsonUrl($context, $content->getPost()->getRedditPostUrl());
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
    private function processJsonCommentsData(Content $content, array $commentsData, Comment $originalComment = null): void
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
     * @param  Context  $context
     * @param  string  $jsonUrl
     *
     * @return array{
     *      postData: array,
     *      commentsData: array,
     *     }
     * @throws InvalidArgumentException
     */
    private function getRawDataFromJsonUrl(Context $context, string $jsonUrl): array
    {
        $jsonData = $this->api->getPostFromJsonUrl($context, $jsonUrl);
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
}
