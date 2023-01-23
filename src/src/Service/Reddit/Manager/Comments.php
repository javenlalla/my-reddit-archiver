<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Denormalizer\CommentWithRepliesDenormalizer;
use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\MoreComment;
use App\Repository\MoreCommentRepository;
use App\Service\Reddit\Api;
use App\Trait\CommentUrlTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;

class Comments
{
    use CommentUrlTrait;

    public function __construct(
        private readonly Api $redditApi,
        private readonly CommentWithRepliesDenormalizer $commentDenormalizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly MoreCommentRepository $moreCommentRepository,
    ) {
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
        $commentsRawResponse = $this->redditApi->getPostCommentsByRedditId(
            redditId: $content->getPost()->getRedditId(),
            sort: Api::COMMENTS_SORT_NEW,
        );

        $commentData = [];
        foreach ($commentsRawResponse as $topLevelRaw) {
            foreach ($topLevelRaw['data']['children'] as $topLevelChildRaw) {
                if ($topLevelChildRaw['kind'] === Kind::KIND_COMMENT) {
                    $commentData = $topLevelChildRaw['data'];
                }
            }
        }

        // If no Comment data found (i.e.: Link Post contained no Comments),
        // return null.
        if (empty($commentData)) {
            return null;
        }

        return $this->commentDenormalizer->denormalize($content->getPost(), Comment::class, null, ['commentData' => $commentData]);
    }

    public function syncCommentsByContent(Content $content): ArrayCollection
    {
        $post = $content->getPost();
        $postRedditId = $post->getRedditId();
        $commentsRawResponse = $this->redditApi->getPostCommentsByRedditId($postRedditId);
        $commentsRawData = $commentsRawResponse[1]['data']['children'];

        foreach ($commentsRawData as $commentRawData) {
            if ($commentRawData['kind'] !== 'more') {
                $commentData = $commentRawData['data'];
                $comment = $this->commentDenormalizer->denormalize($post, Comment::class, null, ['commentData' => $commentData]);

                $this->entityManager->persist($comment);

                $post->addComment($comment);
                $this->entityManager->persist($post);
            } else if ($commentRawData['kind'] === 'more' && !empty($commentRawData['data']['children'])) {

                foreach ($commentRawData['data']['children'] as $moreCommentRedditId) {
                    $moreComment = new MoreComment();
                    $moreComment->setRedditId($moreCommentRedditId);

                    $commentUrl = $this->generateRedditUrl($post, $moreCommentRedditId);
                    $moreComment->setUrl($commentUrl);
                    $this->entityManager->persist($moreComment);

                    $post->addMoreComment($moreComment);
                    $this->entityManager->persist($post);
                }
            }
        }

        $this->entityManager->flush();

        return $post->getTopLevelComments();
    }

    /**
     * @param  string  $redditId
     *
     * @return Comment[]
     * @throws InvalidArgumentException
     */
    public function syncMoreCommentAndRelatedByRedditId(string $redditId): array
    {
        $initialMoreComment = $this->moreCommentRepository->findOneBy(['redditId' => $redditId]);
        if (empty($initialMoreComment)) {
            return [];
        }

        if (!empty($initialMoreComment->getParentComment())) {
            $post = $initialMoreComment->getParentComment()->getParentPost();
            $allMoreComments = $this->moreCommentRepository->findBy(['parentComment' => $initialMoreComment->getParentComment()]);
        } else {
            $post = $initialMoreComment->getParentPost();
            $allMoreComments = $this->moreCommentRepository->findBy(['parentPost' => $initialMoreComment->getParentPost()]);
        }

        $comments = [];
        foreach ($allMoreComments as $moreComment) {
            $moreCommentResponseData = $this->redditApi->getPostFromJsonUrl($moreComment->getUrl());

            // In the case of a More Comment that is "missing" (deleted, removed, etc.)
            // on the Reddit side, delete the More Comment entity and skip processing.
            if (empty($moreCommentResponseData[1]['data']['children'][0])) {
                $this->entityManager->remove($moreComment);
                continue;
            }

            $moreCommentData = $moreCommentResponseData[1]['data']['children'][0]['data'];
            $comment = $this->commentDenormalizer->denormalize($post, Comment::class, null, ['commentData' => $moreCommentData]);
            $this->entityManager->persist($comment);

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
    public function syncAllCommentsByContent(Content $content): ArrayCollection
    {
        $post = $content->getPost();
        $postRedditId = $post->getRedditId();
        $commentsRawResponse = $this->redditApi->getPostCommentsByRedditId($postRedditId);
        $commentsRawData = $commentsRawResponse[1]['data']['children'];

        $commentsData = $this->retrieveAllComments($postRedditId, $commentsRawData);
        foreach ($commentsData as $commentData) {
            $comment = $this->commentDenormalizer->denormalize($post, Comment::class, null, ['commentData' => $commentData]);

            $this->entityManager->persist($comment);

            $post->addComment($comment);
            $this->entityManager->persist($post);
        }

        $this->entityManager->flush();

        return $post->getTopLevelComments();
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
    private function retrieveAllComments(string $postRedditId, array $commentsRawData, array $moreElementData = []): array
    {
        $targetCommentsData = $commentsRawData;
        if (!empty($moreElementData)) {
            $targetCommentsData = $this->redditApi->getMoreChildren($postRedditId, $moreElementData);
        }

        $comments = [];
        foreach ($targetCommentsData as $commentRawData) {
            if ($commentRawData['kind'] === 'more' && !empty($commentRawData['data']['children'])) {
                $extractedMoreComments = $this->retrieveAllComments($postRedditId, $commentsRawData, $commentRawData['data']);

                array_push($comments, ...$extractedMoreComments);
            } else if ($commentRawData['kind'] !== 'more') {
                $comments[] = $commentRawData['data'];
            }
        }

        return $comments;
    }
}
