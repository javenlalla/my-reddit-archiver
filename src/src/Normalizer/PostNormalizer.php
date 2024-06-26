<?php
declare(strict_types=1);

namespace App\Normalizer;

use App\Entity\Asset;
use App\Entity\Post;
use App\Entity\PostAuthorText;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PostNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly CommentNormalizer $commentNormalizer,
        private readonly AssetNormalizer $assetNormalizer,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof Post;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Post::class => true,
        ];
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
            'subreddit' => $post->getSubreddit()->getName(),
            'reddit_url' => $post->getRedditPostUrl(),
            'author' => $post->getAuthor(),
            'author_text' => null,
            'thumbnail' => null,
            'media_assets' => [],
            'comments_count' => $post->getComments()->count(),
            'comments' => [],
            'created_at' => $post->getCreatedAt()->format('Y-m-d H:i:s'),
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
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
            ];
        }

        foreach ($post->getTopLevelComments() as $comment) {
            $normalizedData['comments'][] = $this->commentNormalizer->normalize($comment);
        }

        $thumbnailAsset = $post->getThumbnailAsset();
        if ($thumbnailAsset instanceof Asset) {
            $normalizedData['thumbnail'] = $this->assetNormalizer->normalize($thumbnailAsset);
        }

        foreach ($post->getMediaAssets() as $mediaAsset) {
            $normalizedData['media_assets'][] = $this->assetNormalizer->normalize($mediaAsset);
        }

        return $normalizedData;
    }
}
