<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Entity\Award;
use App\Entity\Comment;
use App\Entity\CommentAward;
use App\Entity\Post;
use App\Service\Reddit\Api;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CommentsAndMoreDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly CommentDenormalizer $commentDenormalizer,
        private readonly Api $api,
        private readonly AwardDenormalizer $awardDenormalizer,
    ){
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        // @TODO: Add additional checks to ensure array is compatible with a Comment Entity.
        return is_array($data) && $type === 'array';
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false,
        ];
    }

    /**
     * Denormalize the provided Response Data containing a Listing of Comments
     * and return an array of Comment Entities.
     *
     * @param  array  $data  Response Data from Reddit API for a particular Comment.
     * @param  string  $type
     * @param  string|null  $format
     * @param  array  $context  'post' => Instance of a Post Entity.
     *                          'parentComment' => Instance of a Comment Entity to which this Comment belongs.
     *
     * @return array
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): array
    {
        $commentsRawData = $data;
        $sanitizedCommentsRawData = $this->retrieveAllComments($context['post'], $commentsRawData);

        $comments = [];
        foreach ($sanitizedCommentsRawData as $commentRawData) {
            $commentData = $commentRawData;
            if (!empty($commentRawData['data'])) {
                $commentData = $commentRawData['data'];
            }

            $comment = $this->commentDenormalizer->denormalize($context['post'], Comment::class, null, ['commentData' => $commentData]);

            if (isset($context['parentComment']) && $context['parentComment'] instanceof Comment) {
                $comment->setParentComment($context['parentComment']);
            }

            if (!empty($commentData['replies'])) {
                $context['parentComment'] = $comment;
                $replies = $this->denormalize($commentData['replies']['data']['children'], 'array', null, $context);

                foreach ($replies as $reply) {
                    $comment->addReply($reply);
                }
            }

            $comments[] = $comment;
        }

        return $comments;
    }

    /**
     * Retrieve all Comments for the provided Post by first inspecting the
     * provided Comments Raw data array and then extracting visible and More
     * Children Comments based on the data.
     *
     * @param  Post  $post
     * @param  array  $commentsRawData
     *
     * @return array
     */
    private function retrieveAllComments(Post $post, array $commentsRawData): array
    {
        $comments = [];
        foreach ($commentsRawData as $commentRawData) {
            if ($commentRawData['kind'] === 'more' && !empty($commentRawData['data']['children'])) {
                $extractedMoreComments = $this->extractMoreCommentChildren($post->getRedditId(), $commentRawData['data']);

                array_push($comments, ...$extractedMoreComments);
            } else if ($commentRawData['kind'] !== 'more') {
                $comments[] = $commentRawData;
            }
        }

        return $comments;
    }

    /**
     * Core function to recursively drill down into 'more' components and extract
     * the relevant Comments within those elements.
     *
     * @param  string  $postRedditId
     * @param  array  $originalMoreRawData
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function extractMoreCommentChildren(string $postRedditId, array $originalMoreRawData): array
    {
        $moreData = $this->api->getMoreChildren($postRedditId, $originalMoreRawData);

        $comments = [];
        foreach ( $moreData as $moreComment) {
            if ($moreComment['kind'] === 'more' && !empty($moreComment['data']['children'])) {
                $extractedMoreComments = $this->extractMoreCommentChildren($postRedditId, $moreComment['data']);

                array_push($comments, ...$extractedMoreComments);
            } else if ($moreComment['kind'] !== 'more'){
                $comments[] = $moreComment;
            }
        }

        return $comments;
    }
}
