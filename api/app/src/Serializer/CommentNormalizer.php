<?php

namespace App\Serializer;

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
        $normalizedData = [
            'id' => $object->getId(),
            'reddit_id' => $object->getRedditId(),
            'author' => $object->getAuthor(),
            'text' => $object->getText(),
            'score' => $object->getScore(),
            'depth' => $object->getDepth(),
            'replies' => [],
        ];

        foreach ($object->getReplies() as $reply) {
            $normalizedData['replies'][] = $this->normalize($reply);
        }

        return $normalizedData;
    }

    /**
     * @param  mixed  $data
     * @param  string|null  $format
     *
     * @return bool
     */
    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Comment;
    }
}
