<?php

namespace App\Serializer;

use App\Entity\Post;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PostNormalizer implements NormalizerInterface
{
    public function __construct(private readonly CommentNormalizer $commentNormalizer)
    {
    }

    /**
     * @param  Post  $object
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
            'type' => $object->getType()->getRedditTypeId(),
            'content_type' => $object->getContentType()->getName(),
            'title' => $object->getTitle(),
            'score' => $object->getScore(),
            'url' => $object->getUrl(),
            'author' => $object->getAuthor(),
            'subreddit' => $object->getSubreddit(),
            'reddit_post_id' => $object->getRedditPostId(),
            'reddit_post_url' => $object->getRedditPostUrl(),
            'author_text' => $object->getAuthorText(),
            'author_text_html' => $object->getAuthorTextHtml(),
            'author_text_raw_html' => $object->getAuthorTextRawHtml(),
            'created_at' => $object->getCreatedAt()->format('Y-m-d H:i:s'),
            'comments' => [],
        ];

        foreach ($object->getTopLevelComments() as $comment) {
            $normalizedData['comments'][] = $this->commentNormalizer->normalize($comment);
        }

        return $normalizedData;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof Post;
    }
}
