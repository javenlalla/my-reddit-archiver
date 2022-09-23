<?php

namespace App\Denormalizer;

use App\Entity\Comment;
use App\Entity\Post;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CommentDenormalizer implements DenormalizerInterface
{
    /**
     * @param  Post  $data
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
        $post = $data;
        $commentData = $context['commentData'];

        $comment = new Comment();
        $comment->setRedditId($commentData['id']);
        $comment->setScore((int) $commentData['score']);
        $comment->setText($commentData['body']);
        $comment->setAuthor($commentData['author']);
        $comment->setParentPost($post);
        $comment->setDepth((int) $commentData['depth']);

        if (isset($context['parentComment']) && $context['parentComment'] instanceof Comment) {
            $comment->setParentComment($context['parentComment']);
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
