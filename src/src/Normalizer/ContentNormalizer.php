<?php

namespace App\Normalizer;

use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\Post;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ContentNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly PostNormalizer $postNormalizer,
        private readonly CommentNormalizer $commentNormalizer,
    ) {
    }

    /**
     * @param  Content  $object
     * @param  string|null  $format
     * @param  array  $context
     *
     * @return array
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $content = $object;
        $kind = $content->getKind();
        $post = $content->getPost();

        $normalizedData = [
            'id' => $content->getId(),
            'kind' => [
                'reddit_id' => $kind->getRedditKindId(),
                'name' => $kind->getName(),
            ],
            'comment' => null,
        ];

        $normalizedData['post'] = $this->postNormalizer->normalize($post);

        if ($kind->getRedditKindId() === Kind::KIND_COMMENT) {
            $normalizedData['comment'] = $this->commentNormalizer->normalize($content->getComment());
        }

        return $normalizedData;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof Content;
    }
}
