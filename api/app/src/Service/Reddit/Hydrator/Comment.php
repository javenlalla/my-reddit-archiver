<?php

namespace App\Service\Reddit\Hydrator;

use App\Entity\Comment as CommentEntity;
use App\Entity\Post;
use App\Service\Reddit\Api;

class Comment
{
    public function __construct(private readonly Api $api){}

    /**
     * Instantiate and hydrate Comment Entities based on the provided Post
     * and Comments raw data.
     *
     * @param  Post  $post
     * @param  array  $commentsRawData
     * @param  CommentEntity|null  $parentComment
     *
     * @return array
     */
    public function hydrateComments(Post $post, array $commentsRawData, CommentEntity $parentComment = null): array
    {
        $sanitizedCommentsRawData = $this->extractMoreChildrenData($post, $commentsRawData);

        $comments = [];
        foreach ($sanitizedCommentsRawData as $commentRawData) {
            $commentData = $commentRawData;
            if (!empty($commentRawData['data'])) {
                $commentData = $commentRawData['data'];
            }

            $comment = new CommentEntity();
            $comment->setRedditId($commentData['id']);
            $comment->setScore((int) $commentData['score']);
            $comment->setText($commentData['body']);
            $comment->setAuthor($commentData['author']);
            $comment->setParentPost($post);
            if ($parentComment instanceof CommentEntity) {
                $comment->setParentComment($parentComment);
            }

            if (!empty($commentData['replies'])) {
                $replies = $this->hydrateComments($post, $commentData['replies']['data']['children'], $comment);

                foreach ($replies as $reply) {
                    $comment->addReply($reply);
                }
            }

            $comments[] = $comment;
        }

        return $comments;
    }

    /**
     * Inspected the provided Comments Raw data array and extract visible and
     * More Children Comments based on the data.
     *
     * @param  Post  $post
     * @param  array  $commentsRawData
     *
     * @return array
     */
    private function extractMoreChildrenData(Post $post, array $commentsRawData): array
    {
        $comments = [];
        foreach ($commentsRawData as $commentRawData) {
            if ($commentRawData['kind'] === 'more') {
                $extractedMoreComments = $this->executeExtractMoreChildrenData($post->getRedditId(), $commentRawData['data']);

                array_push($comments, ...$extractedMoreComments);
            } else {
                $comments[] = $commentRawData;
            }
        }

        return $comments;
    }

    /**
     * @param  string  $postRedditId
     * @param  array  $originalMoreRawData
     *
     * @return array
     */
    private function executeExtractMoreChildrenData(string $postRedditId, array $originalMoreRawData): array
    {
        $moreData = $this->api->getMoreChildren($postRedditId, $originalMoreRawData);

        $comments = [];
        foreach ( $moreData['json']['data']['things'] as $moreComment) {
            if ($moreComment['kind'] === 'more' && !empty($moreComment['data']['children'])) {
                $extractedMoreComments = $this->executeExtractMoreChildrenData($postRedditId, $moreComment['data']);

                array_push($comments, ...$extractedMoreComments);
            } else if ($moreComment['kind'] !== 'more'){
                $comments[] = $moreComment;
            }
        }

        return $comments;
    }
}
