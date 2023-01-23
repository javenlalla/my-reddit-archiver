<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Denormalizer\CommentWithRepliesDenormalizer;
use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;
use App\Service\Reddit\Api;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;

class Comments
{
    public function __construct(
        private readonly Api $redditApi,
        private readonly CommentWithRepliesDenormalizer $commentDenormalizer,
        private readonly EntityManagerInterface $entityManager
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
