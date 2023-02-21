<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Denormalizer\MediaAssets\MediaMetadataDenormalizer;
use App\Denormalizer\MediaAssets\RedditVideoDenormalizer;
use App\Entity\Asset;
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
        private readonly AssetDenormalizer $assetDenormalizer,
        private readonly MediaMetadataDenormalizer $mediaMetadataDenormalizer,
        private readonly RedditVideoDenormalizer $redditVideoDenormalizer,
    ) {
    }

    /**
     * Based on the provided Post, inspect its properties and denormalize its
     * associated Response Data in order to return a Media Asset Entity.
     *
     * @param  string  $data Source URL
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              postType: Type,
     *              mediasMetadata: array,
     *              isVideo: bool,
     *              videoSourceUrl: ?string,
     *              isGif: bool,
     *              gifSourceUrl: ?string,
     *          } $context  'postResponseData' contains the original API Response Data for this Post.
     *
     * @return Asset[]
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): array
    {
        $sourceUrl = $data;
        $type = $context['postType'];

        $mediaAssets = [];
        if (in_array($type->getName(), self::BASE_DENORMALIZER_CONTENT_TYPES)) {
            $targetSourceUrl = $sourceUrl;
            if (!empty($context['gifSourceUrl'])) {
                $targetSourceUrl = $context['gifSourceUrl'];
            }

            $mediaAsset = $this->assetDenormalizer->denormalize($targetSourceUrl, Asset::class, null, $context);
            if ($mediaAsset instanceof Asset) {
                $mediaAssets[] = $mediaAsset;
            }
        }

        if ($type->getName() === Type::CONTENT_TYPE_IMAGE_GALLERY || !empty($context['mediasMetadata'])) {
            $mediaAssets = $this->mediaMetadataDenormalizer->denormalize($context['mediasMetadata'], Asset::class, null, $context);
        }

        if ($type->getName() === Type::CONTENT_TYPE_VIDEO && $context['isVideo'] === true) {
            $mediaAsset = $this->redditVideoDenormalizer->denormalize($sourceUrl, Asset::class, null, $context);
            if ($mediaAsset instanceof Asset) {
                $mediaAssets[] = $mediaAsset;
            }
        }

        return $mediaAssets;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return is_string($data) && $type === Asset::class;
    }
}
