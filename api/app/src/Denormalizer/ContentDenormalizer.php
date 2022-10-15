<?php

namespace App\Denormalizer;

use App\Denormalizer\Post\CommentPostDenormalizer;
use App\Denormalizer\Post\LinkPostDenormalizer;
use App\Entity\Post;
use App\Entity\Content;
use App\Entity\Type;
use Exception;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ContentDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly LinkPostDenormalizer $linkPostDenormalizer,
        private readonly CommentPostDenormalizer $commentPostDenormalizer,
    ) {
    }

    /**
     * @param  array  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array|null  $context
     *
     * @return bool
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = null): bool
    {
        return is_array($data) && $type === Content::class;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Content
    {
        if ($data['kind'] === 'Listing') {
            $data = $data['data']['children'][0];
        }

        if ($data['kind'] === Type::TYPE_LINK) {
            return $this->linkPostDenormalizer->denormalize($data['data'], Post::class);
        } elseif ($data['kind'] === Type::TYPE_COMMENT) {
            return $this->commentPostDenormalizer->denormalize($data['data'], Post::class, null, ['parentPost' => $context['parentPostData']['data']['children'][0]['data']]);
        }

        throw new Exception(sprintf('Unexpected Post type %s: %s', $data['kind'], var_export($data, true)));
    }
}
