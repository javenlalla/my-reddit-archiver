<?php
declare(strict_types=1);

namespace App\Normalizer;

use App\Entity\Comment;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CommentNormalizer implements NormalizerInterface
{

    /**
     * @param  Comment  $object
     * @param  string|null  $format
     * @param  array  $context
     *
     * @return array
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $comment = $object;

        $normalizedData = [
            'id' => $comment->getId(),
            'reddit_id' => $comment->getRedditId(),
            'author' => $comment->getAuthor(),
            'score' => $comment->getScore(),
            'depth' => $comment->getDepth(),
            'reply_count' => $comment->getReplies()->count(),
        ];

        $latestCommentAuthorText = $comment->getLatestCommentAuthorText();
        $latestAuthorText = $latestCommentAuthorText->getAuthorText();
        $normalizedData['author_text'] = [
            'created_at' => $latestCommentAuthorText->getCreatedAt(),
            'text' => $latestAuthorText->getText(),
            'text_html' => $latestAuthorText->getTextHtml(),
            'text_raw_html' => $latestAuthorText->getTextRawHtml(),
        ];

        return $normalizedData;
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof Comment;
    }
}
