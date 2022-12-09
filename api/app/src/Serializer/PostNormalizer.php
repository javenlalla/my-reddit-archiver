<?php

namespace App\Serializer;

use App\Entity\Post;
use App\Entity\PostAuthorText;
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
        $post = $object;

        $normalizedData = [
            'id' => $post->getId(),
            'reddit_id' => $post->getRedditId(),
            'kind' => $post->getContent()->getKind()->getName(),
            'type' => $post->getType()->getName(),
            'title' => $post->getTitle(),
            'score' => $post->getScore(),
            'url' => $post->getUrl(),
            'subreddit' => $post->getSubreddit(),
            'reddit_post_id' => $post->getRedditId(),
            'reddit_post_url' => $post->getUrl(),
            'author' => $post->getAuthor(),
            'author_text' => [],
            'created_at' => $post->getCreatedAt()->format('Y-m-d H:i:s'),
            'comments' => [],
        ];

        // @TODO: Move this logic and empty array logic to an Author Text Normalizer.
        $postAuthorText = $post->getLatestPostAuthorText();
        if ($postAuthorText instanceof PostAuthorText) {
            $createdAt = $postAuthorText->getCreatedAt();
            $authorText = $postAuthorText->getAuthorText();

            $normalizedData['author_text'] = [
                'text' => $authorText->getText(),
                'textHtml' => $authorText->getTextHtml(),
                'textRawHtml' => $authorText->getTextRawHtml(),
            ];
        }

        // foreach ($post->getTopLevelComments() as $comment) {
        //     $normalizedData['comments'][] = $this->commentNormalizer->normalize($comment);
        // }

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
