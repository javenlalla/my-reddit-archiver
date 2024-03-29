<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Denormalizer\CommentDenormalizer;
use App\Denormalizer\CommentWithRepliesDenormalizer;
use App\Denormalizer\MoreCommentDenormalizer;
use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\MoreComment;
use App\Entity\Post;
use App\Repository\CommentRepository;
use App\Repository\ContentRepository;
use App\Repository\MoreCommentRepository;
use App\Service\Reddit\Api;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Items;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;

class Comments
{
    public function __construct(
        private readonly Api $redditApi,
        private readonly Items $itemsService,
        private readonly CommentDenormalizer $commentDenormalizer,
        private readonly CommentWithRepliesDenormalizer $commentWithRepliesDenormalizer,
        private readonly MoreCommentDenormalizer $moreCommentDenormalizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly MoreCommentRepository $moreCommentRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ContentRepository $contentRepository,
    ) {
    }

    /**
     * Sync the Parent Comment, if any, of the following Comment Entity.
     *
     * @param  Context  $context
     * @param  Comment  $comment
     *
     * @return Comment|null
     */
    public function syncParentComment(Context $context, Comment $comment): ?Comment
    {
        $parentComment = $comment->getParentComment();
        if ($parentComment instanceof Comment) {
            return $parentComment;
        }

        // If there is no parent Comment Reddit ID associated, return null as
        // there is no parent to sync.
        if (empty($comment->getParentCommentRedditId())) {
            return null;
        }

        $parentItemJson = $this->itemsService->getItemInfoByRedditId(
            $context,
            $comment->getParentCommentRedditId()
        );

        $parentComment = $this->commentDenormalizer->denormalize(
            $comment->getParentPost(),
            Comment::class,
            null,
            ['commentData' => $parentItemJson->getJsonBodyArray()]
        );

        $this->linkCommentToParent($comment, $parentComment);

        return $parentComment;
    }

    /**
     * Sync the replies, if any, of the provided Comment.
     *
     * @param  Context  $context
     * @param  Comment  $comment
     *
     * @return Collection&Comment[]
     */
    public function syncCommentReplies(Context $context, Comment $comment): Collection
    {
        $commentData = $this->redditApi->getPostCommentsByRedditId($context, redditId: $comment->getParentPost()->getRedditId(), byComment: $comment);

        if (!empty($commentData[0]['data']['replies']['data']['children'])) {
            $this->persistRepliesData(
                $comment,
                $commentData[0]['data']['replies']['data']['children'],
            );

            $comment->setHasReplies(true);
        } else {
            $comment->setHasReplies(false);
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment->getReplies();
    }

    /**
     * Retrieve the latest Comment on the provided Content by calling the API
     * and sorting by `New`.
     *
     * @param  Content  $content
     *
     * @return Comment|null
     * @throws InvalidArgumentException
     */
    public function getLatestCommentByContent(Content $content): ?Comment
    {
        $commentsRawData = $this->redditApi->getPostCommentsByRedditId(
            redditId: $content->getPost()->getRedditId(),
            sort: Api::COMMENTS_SORT_NEW,
        );

        if (!empty($commentsRawData[0]['data'])) {
            $commentData = $commentsRawData[0]['data'];
        } else {
            // If no Comment data found (i.e.: Link Post contained no Comments),
            // return null.
            return null;
        }

        return $this->commentDenormalizer->denormalize($content->getPost(), Comment::class, null, ['commentData' => $commentData]);
    }

    /**
     * Sync a set of Comments associated to the provided Content.
     *
     * Not all Comments are immediately synced; those that are set as "more"
     * will instead be persisted as More Comment Entities for syncing at a
     * later time.
     *
     * @param  Context  $context
     * @param  Content  $content
     *
     * @return ArrayCollection
     * @throws InvalidArgumentException
     */
    public function syncCommentsByContent(Context $context, Content $content): ArrayCollection
    {
        $post = $content->getPost();
        $postRedditId = $post->getRedditId();
        $commentsRawData = $this->redditApi->getPostCommentsByRedditId($context, $postRedditId);

        foreach ($commentsRawData as $commentRawData) {
            if ($commentRawData['kind'] !== 'more') {
                $commentData = $commentRawData['data'];
                $comment = $this->commentWithRepliesDenormalizer->denormalize($post, Comment::class, null, ['commentData' => $commentData]);

                $this->entityManager->persist($comment);

                $post->addComment($comment);
                $this->entityManager->persist($post);
            } else if ($commentRawData['kind'] === 'more' && !empty($commentRawData['data']['children'])) {

                foreach ($commentRawData['data']['children'] as $moreCommentRedditId) {
                    // Only create a new More Comment Entity if it has not
                    // already been synced as a Comment.
                    if (empty($this->commentRepository->findOneBy(['redditId' => $moreCommentRedditId]))) {
                        $moreComment = $this->moreCommentDenormalizer->denormalize($moreCommentRedditId, MoreComment::class, null, ['post' => $post]);
                        $this->entityManager->persist($moreComment);

                        $post->addMoreComment($moreComment);
                        $this->entityManager->persist($post);
                    }
                }
            }
        }

        $this->entityManager->flush();

        return $post->getTopLevelComments();
    }

    /**
     * Sync More Comment Entities related to the provided More Comment Reddit
     * ID.
     *
     * Fetch all related More Comment Entities first, by Comment or Post, and
     * then sync each Entity.
     *
     * @param  Context  $context
     * @param  string  $redditId
     * @param  int  $limit
     *
     * @return Comment[]
     * @throws InvalidArgumentException
     */
    public function syncMoreCommentAndRelatedByRedditId(Context $context, string $redditId, int $limit = MoreCommentRepository::DEFAULT_LIMIT): array
    {
        $initialMoreComment = $this->moreCommentRepository->findOneBy(['redditId' => $redditId]);
        if (empty($initialMoreComment)) {
            return [];
        }

        $parentComment = null;
        if (!empty($initialMoreComment->getParentComment())) {
            $parentComment = $initialMoreComment->getParentComment();
            $post = $initialMoreComment->getParentComment()->getParentPost();
            $allMoreComments = $this->moreCommentRepository->findByRelatedParentComment($initialMoreComment, $limit);
        } else {
            $post = $initialMoreComment->getParentPost();
            $allMoreComments = $this->moreCommentRepository->findByRelatedParentPost($initialMoreComment, $limit);
        }

        $comments = [];
        foreach ($allMoreComments as $moreComment) {
            $moreCommentResponseData = $this->redditApi->getPostFromJsonUrl($context, $moreComment->getUrl());

            // In the case of a More Comment that is "missing" (deleted, removed, etc.)
            // on the Reddit side, delete the More Comment entity and skip processing.
            if (empty($moreCommentResponseData[1]['data']['children'][0])) {
                $this->entityManager->remove($moreComment);
                continue;
            }

            $moreCommentData = $moreCommentResponseData[1]['data']['children'][0]['data'];
            $comment = $this->commentWithRepliesDenormalizer->denormalize($post, Comment::class, null, ['commentData' => $moreCommentData]);
            $this->entityManager->persist($comment);

            if (!empty($parentComment)) {
                $parentComment->addReply($comment);
                $this->entityManager->persist($parentComment);
            }

            // Remove More Comment to avoid unnecessary subsequent syncs.
            $this->entityManager->remove($moreComment);

            $comments[] = $comment;
        }

        $this->entityManager->flush();

        return $comments;
    }

    /**
     * Retrieve all Comments under the provided Content, including "More"
     * Comments.
     *
     * @param  Content  $content
     *
     * @return ArrayCollection
     * @throws InvalidArgumentException
     */
    public function syncAllCommentsByContent(Context $context, Content $content): ArrayCollection
    {
        $post = $content->getPost();
        $postRedditId = $post->getRedditId();
        $commentsRawData = $this->redditApi->getPostCommentsByRedditId($context, $postRedditId);

        $commentsData = $this->retrieveAllComments($context, $postRedditId, $commentsRawData);
        foreach ($commentsData as $commentData) {
            $comment = $this->commentWithRepliesDenormalizer->denormalize($post, Comment::class, null, ['commentData' => $commentData]);

            $this->entityManager->persist($comment);

            $post->addComment($comment);
            $this->entityManager->persist($post);
        }

        $this->entityManager->flush();

        return $post->getTopLevelComments();
    }

    /**
     * Get all Comments associated to the provided Post.
     *
     * By default, since this is a Post Entity, sort and return only top-level
     * Comments.
     *
     * @param  Post  $post
     * @param  bool  $topLevelCommentsOnly
     * @param  bool  $prioritizeContentComments Set to true if it is desired
     *                  that Comments under the Post that have been saved as
     *                  Contents are pushed to the beginning of the order.
     *
     * @return Comment[]
     */
    public function getOrderedCommentsByPost(Post $post, bool $topLevelCommentsOnly = true, bool $prioritizeContentComments = false): array
    {
        $orderedComments = $this->commentRepository->getOrderedCommentsByPost($post, $topLevelCommentsOnly);

        if ($prioritizeContentComments === true) {
            return $this->prioritizeContentComments($post, $orderedComments);
        }

        return $orderedComments;
    }

    /**
     * Retrieve all Comments for the provided Post Reddit ID by first inspecting
     * the Comments Raw data array and then extracting visible and More
     * Children Comments based on the data.
     *
     * @param  string  $postRedditId
     * @param  array  $commentsRawData
     * @param  array  $moreElementData
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function retrieveAllComments(Context $context, string $postRedditId, array $commentsRawData, array $moreElementData = []): array
    {
        $targetCommentsData = $commentsRawData;
        if (!empty($moreElementData)) {
            $targetCommentsData = $this->redditApi->getMoreChildren($context, $postRedditId, $moreElementData);
        }

        $comments = [];
        foreach ($targetCommentsData as $commentRawData) {
            if ($commentRawData['kind'] === 'more' && !empty($commentRawData['data']['children'])) {
                $extractedMoreComments = $this->retrieveAllComments($context, $postRedditId, $commentsRawData, $commentRawData['data']);

                array_push($comments, ...$extractedMoreComments);
            } else if ($commentRawData['kind'] !== 'more') {
                $comments[] = $commentRawData['data'];
            }
        }

        return $comments;
    }

    /**
     * Push Comments under the Post that have been saved as Contents to the
     * beginning of the provided ordered Comments array.
     * @param  Post  $post
     * @param  array  $orderedComments
     *
     * @return array
     */
    private function prioritizeContentComments(Post $post, array $orderedComments = []): array
    {
        $contentComments = $this->contentRepository->fetchCommentContentsByPost($post);
        if (empty($contentComments)) {
            // No Content Comments to prioritize, so return the ordered
            // Comments as is.
            return $orderedComments;
        }

        $prioritizedComments = [];
        $contentCommentIds = [];
        foreach ($contentComments as $contentComment) {
            $comment = $contentComment->getComment();
            $rootComment = $comment->getRootComment();

            if ($rootComment instanceof Comment) {
                $prioritizedComments[] = $rootComment;
                $contentCommentIds[] = $rootComment->getId();
            } else {
                $prioritizedComments[] = $comment;
                $contentCommentIds[] = $comment->getId();
            }
        }

        foreach ($orderedComments as $orderedComment) {
            if (!in_array($orderedComment->getId(), $contentCommentIds)) {
                $prioritizedComments[] = $orderedComment;
            }
        }

        return $prioritizedComments;
    }

    /**
     * Link the provided Comment to the targeted parent Comment and persist the
     * updated Entity.
     *
     * @param  Comment  $comment
     * @param  Comment  $parentComment
     *
     * @return void
     */
    private function linkCommentToParent(Comment $comment, Comment $parentComment): void
    {
        $comment->setParentComment($parentComment);
        $this->entityManager->persist($comment);

        $parentComment->addReply($comment);
        $this->entityManager->persist($parentComment);

        $this->entityManager->flush();
    }

    /**
     * Denormalize and persist the Replies data associated to the targeted
     * Comment.
     *
     * @param  Comment  $comment
     * @param  array  $repliesData
     *
     * @return void
     */
    private function persistRepliesData(Comment $comment, array $repliesData): void
    {
        $flushBatchSize = 100;
        $flushBatchCount = 0;
        foreach ($repliesData as $replyData) {
            if (!empty($replyData['kind']) && $replyData['kind'] === Kind::KIND_COMMENT) {
                $reply = $this->commentDenormalizer->denormalize($comment->getParentPost(), Comment::class, null, ['commentData' => $replyData]);

                $reply->setParentComment($comment);
                $comment->addReply($reply);

                $this->entityManager->persist($reply);
                $this->entityManager->persist($comment);

                $flushBatchCount++;
                if (($flushBatchCount % $flushBatchSize) === 0) {
                    $this->entityManager->flush();
                    $flushBatchCount = 0;
                }
            }
        }

        if ($flushBatchCount > 0) {
            $this->entityManager->flush();
        }
    }
}
