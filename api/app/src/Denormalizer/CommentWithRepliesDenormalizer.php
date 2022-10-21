<?php

namespace App\Denormalizer;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Post;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CommentWithRepliesDenormalizer implements DenormalizerInterface
{
    public function __construct(private readonly CommentDenormalizer $commentDenormalizer)
    {
    }

    /**
     * Denormalize a Comment and its Replies using the provided Post and Response
     * Data.
     *
     * @param  Content  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              commentData: array
     *          } $context  `commentData` Original Response data for this Comment.
     *
     * @return Comment
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Comment
    {
        $comment = $this->commentDenormalizer->denormalize($data, $type, $format, $context);
        $content = $data;
        $commentData = $context['commentData'];

        if (!empty($commentData['replies'])) {
            $context['parentComment'] = $comment;

            foreach ($commentData['replies']['data']['children'] as $replyCommentData) {
                if ($replyCommentData['kind'] !== 'more') {
                    $context['commentData'] = $replyCommentData['data'];

                    $reply = $this->denormalize($content, $type, $format, $context);
                    $comment->addReply($reply);
                }
            }
        }

        return $comment;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post && $type === Comment::class;
    }
}
