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
use App\Helper\RedditIdHelper;
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
    public const COMMENT_URL_REGEX_PATTERN = '/comments\/[a-zA-Z0-9]{4,10}\/[[:word:]_]*\/[a-zA-Z0-9]{4,10}/iu';

    public function __construct(
        private readonly Api $api,
        private readonly Items $itemsService,
        private readonly Contents $contentsManager,
        private readonly ContentRepository $contentRepository,
        private readonly CommentRepository $commentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CommentsAndMoreDenormalizer $commentsAndMoreDenormalizer,
        private readonly CommentWithRepliesDenormalizer $commentDenormalizer,
        private readonly CommentDenormalizer $commentNoRepliesDenormalizer,
        private readonly RedditIdHelper $redditIdHelper,
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
        $response = $this->itemsService->getItemInfoByRedditId($context, $fullRedditId)->getJsonBodyArray();
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
     * @param  string  $redditKindId
     * @param  string  $postLink
     * @param  bool  $syncComments
     * @param  bool  $downloadAssets
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function syncContentFromJsonUrl(Context $context, string $redditKindId, string $postLink, bool $syncComments = false, bool $downloadAssets = false): Content
    {
        $postRedditId = $this->redditIdHelper->extractRedditIdFromUrl(Kind::KIND_LINK, $postLink);
        $postItemJson = $this->itemsService->getItemInfoByRedditId($context, $postRedditId);
        $postItemInfo = $postItemJson->getJsonBodyArray();

        if ($redditKindId === Kind::KIND_COMMENT) {
            $commentRedditId = $this->redditIdHelper->extractRedditIdFromUrl($redditKindId, $postLink);

            return $this->persistCommentPostJsonUrlData($context, $postItemInfo, $commentRedditId);
        }

        return $this->persistLinkContentJsonUrlData($context, $postItemInfo, $syncComments, $downloadAssets);
    }

    /**
     * Instantiate and hydrate a Content Entity based on the provided Response data.
     *
     * Additionally, retrieve the parent Post from the API if the provided
     * Response is of Comment `kind`.
     *
     * @param  Context  $context
     * @param  string  $type
     * @param  array  $response
     * @param  bool  $downloadAssets
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function hydrateContentFromResponseData(Context $context, string $type, array $response, bool $downloadAssets = false): Content
    {
        $parentPostResponse = [];

        if ($type === Kind::KIND_COMMENT && $response['kind'] === 'Listing') {
            $parentPostResponse = $this->itemsService->getItemInfoByRedditId($context, $response['data']['children'][0]['data']['link_id'])->getJsonBodyArray();
        } else if ($type === Kind::KIND_COMMENT && $response['kind'] === Kind::KIND_COMMENT) {
            $parentPostResponse = $this->itemsService->getItemInfoByRedditId($context, $response['data']['link_id'])->getJsonBodyArray();
        }

        return $this->contentsManager->parseAndDenormalizeContent($context, $response, ['parentPostData' => $parentPostResponse, 'downloadAssets' => $downloadAssets]);
    }

    // private function syncCommentTreeBranch(Context $context, Content $content, array $postData, array $commentData): Comment
    // {
    //     $comment = $content->getComment();
    //
    //     // Sync Comment's Parents.
    //     $this->syncCommentWithParents($context, $content, $comment, $postData, $commentData);
    //
    //     // Sync Comment's Replies, if any.
    //     if (isset($commentData['replies']) && !empty($commentData['replies']['data']['children'])) {
    //         $replies = $this->commentsAndMoreDenormalizer->denormalize($commentData['replies']['data']['children'], 'array', null, ['post' => $content->getPost(), 'parentComment' => $comment]);
    //
    //         foreach ($replies as $reply) {
    //             $existingComment = $this->commentRepository->findOneBy(['redditId' => $reply->getRedditId()]);
    //
    //             if (empty($existingComment)) {
    //                 $comment->addReply($reply);
    //                 $this->entityManager->persist($reply);
    //             }
    //         }
    //     }
    //
    //     $this->entityManager->persist($comment);
    //     $this->entityManager->flush();
    //
    //     return $comment;
    // }

    // private function syncCommentWithParents(Context $context, Content $content, Comment $originalComment, array $postData, array $commentData, ?Comment $childComment = null): void
    // {
    //     $post = $content->getPost();
    //     $comment = $this->commentNoRepliesDenormalizer->denormalize($post, Post::class, null, ['commentData' => $commentData]);
    //
    //     $existingComment = $this->commentRepository->findOneBy(['redditId' => $comment->getRedditId()]);
    //     if (!empty($existingComment)) {
    //         $comment = $existingComment;
    //     }
    //
    //     // Do not re-persist the original Comment.
    //     if ($originalComment->getRedditId() !== $comment->getRedditId()) {
    //         if (!empty($childComment)) {
    //             $comment->addReply($childComment);
    //             $childComment->setParentComment($comment);
    //             $this->entityManager->persist($childComment);
    //         }
    //
    //         $post->addComment($comment);
    //
    //         $this->entityManager->persist($comment);
    //         $this->entityManager->persist($post);
    //         $this->entityManager->flush();
    //     }
    //
    //     // Sync parent Comments, if any.
    //     if (!empty($commentData['parent_id']) && $this->redditFullIdIsComment($commentData['parent_id'])) {
    //         $originalPostLink = $postData['permalink'];
    //         $parentId = str_replace('t1_', '', $commentData['parent_id']);
    //         $targetCommentLink = $originalPostLink . $parentId;
    //
    //         $jsonData = $this->api->getPostFromJsonUrl($context, $targetCommentLink);
    //         if (count($jsonData) !== 2) {
    //             throw new Exception(sprintf('Unexpected body count for JSON URL: %s', $targetCommentLink));
    //         }
    //
    //         $commentsData = $jsonData[1]['data']['children'];
    //
    //         $childComment = $comment;
    //         if ($originalComment->getRedditId() === $comment->getRedditId()) {
    //             $childComment = $originalComment;
    //         }
    //
    //         $this->syncCommentWithParents($context, $content, $originalComment, $postData, $commentsData[0]['data'], $childComment);
    //     }
    // }

    // /**
    //  * Verify if the provided full Reddit ID (Ex: t1_ip7pedq) is a Comment ID.
    //  *
    //  * @param  string  $id
    //  *
    //  * @return bool
    //  */
    // private function redditFullIdIsComment(string $id): bool
    // {
    //     $targetPrefix = 't1_';
    //
    //     if (str_starts_with($id, $targetPrefix)) {
    //         return true;
    //     }
    //
    //     return false;
    // }

    /**
     * Persist the following Post and Comment data for a Link Post as
     * retrieved from the Post's JSON URL.
     *
     * @param  Context  $context
     * @param  array  $postData
     * @param  bool  $syncComments
     * @param  bool  $downloadAssets
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    private function persistLinkContentJsonUrlData(Context $context, array $postData, bool $syncComments = false, bool $downloadAssets = false): Content
    {
        $content = $this->hydrateContentFromResponseData($context, $postData['kind'], $postData, $downloadAssets);

        $this->contentRepository->add($content, true);

        if ($syncComments === true) {
            // Disabled to prevent automatic unexpected calls to Reddit API.
            // Syncing Comments will instead be handled by user-triggered behavior.
            // $this->processJsonCommentsData($content, $commentsData);
        }

        $this->entityManager->flush();

        return $content;
    }

    /**
     * Persist the following Post and Comment data for a Comment Post as
     * retrieved from the Post's JSON URL.
     *
     * @param  Context  $context
     * @param  array  $postItemInfo
     * @param  string  $commentRedditId
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    private function persistCommentPostJsonUrlData(Context $context, array $postItemInfo, string $commentRedditId): Content
    {
        $commentItemJson = $this->itemsService->getItemInfoByRedditId($context, $commentRedditId);
        $targetComment = $commentItemJson->getJsonBodyArray();

        $content = $this->contentsManager->parseAndDenormalizeContent($context, $postItemInfo, ['commentData' => $targetComment]);
        $existingContent = $this->contentRepository->findOneBy(['comment' => $content->getComment()]);
        if ($existingContent instanceof Content) {
            $content = $existingContent;
        } else {
            $this->contentRepository->add($content, true);
        }

        // Disabled to prevent automatic unexpected calls to Reddit API.
        // Syncing Comments will instead be handled by user-triggered behavior.
        // $originalComment = $this->syncCommentTreeBranch($context, $content, $postItemInfo['data'], $targetComment);
        // $jsonData = $this->getRawDataFromJsonUrl($context, $content->getPost()->getRedditPostUrl());
        // $this->processJsonCommentsData($content, $jsonData['commentsData'], $originalComment);

        $this->entityManager->flush();

        return $content;
    }
}
