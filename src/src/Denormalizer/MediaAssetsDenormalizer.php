<?php

namespace App\Denormalizer;

use App\Denormalizer\MediaAssets\BaseDenormalizer;
use App\Denormalizer\MediaAssets\MediaMetadataDenormalizer;
use App\Denormalizer\MediaAssets\RedditVideoDenormalizer;
use App\Entity\Content;
use App\Entity\MediaAsset;
use App\Entity\Post;
use App\Entity\Type;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class MediaAssetsDenormalizer implements DenormalizerInterface
{
    private const BASE_DENORMALIZER_CONTENT_TYPES = [
        Type::CONTENT_TYPE_IMAGE,
        Type::CONTENT_TYPE_GIF,
    ];

    public function __construct(
        private readonly BaseDenormalizer $baseDenormalizer,
        private readonly MediaMetadataDenormalizer $mediaMetadataDenormalizer,
        private readonly RedditVideoDenormalizer $redditVideoDenormalizer,
    ) {
    }

    /**
     * Based on the provided Post, inspect its properties and denormalize its
     * associated Response Data in order to return a Media Asset Entity.
     *
     * @param  Post  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              postResponseData: array,
     *          } $context  'postResponseData' contains the original API Response Data for this Post.
     *
     * @return MediaAsset[]
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): array
    {
        $post = $data;
        $responseData = $context['postResponseData'];
        $type = $data->getType();

        $mediaAssets = [];
        if (in_array($type->getName(), self::BASE_DENORMALIZER_CONTENT_TYPES)) {
            $mediaAssets[] = $this->baseDenormalizer->denormalize($post, MediaAsset::class, null, $context);
        }

        if ($type->getName() === Type::CONTENT_TYPE_IMAGE_GALLERY || !empty($responseData['media_metadata'])) {
            $mediaAssets = $this->mediaMetadataDenormalizer->denormalize($post, MediaAsset::class, null, $context);
        }

        if ($type->getName() === Type::CONTENT_TYPE_VIDEO && $responseData['is_video'] === true) {
            $mediaAssets[] = $this->redditVideoDenormalizer->denormalize($post, MediaAsset::class, null, $context);
        }

        return $mediaAssets;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post && $type === MediaAsset::class;
    }
}
