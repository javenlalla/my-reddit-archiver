<?php

namespace App\Denormalizer\MediaAssets;

use App\Entity\MediaAsset;
use App\Entity\Post;
use App\Helper\MediaMetadataHelper;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class MediaMetadataDenormalizer implements DenormalizerInterface
{
    public function __construct(private readonly BaseDenormalizer $baseDenormalizer, private readonly MediaMetadataHelper $mediaMetadataHelper)
    {
    }

    /**
     * Analyze the provided Post and denormalize the associated Response data
     * for an Image Gallery or Media Metadata array into Media Asset Entities.
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

        $mediaAssets = [];
        foreach ($responseData["media_metadata"] as $assetId => $mediaMetadata) {
            $extension = $this->mediaMetadataHelper->extractExtensionFromMediaMetadata($mediaMetadata);
            if ($extension === 'mp4') {
                $sourceUrl = html_entity_decode($mediaMetadata['s']['mp4']);
            } else {
                $sourceUrl = html_entity_decode($mediaMetadata['s']['u']);
            }

            $context['assetExtension'] = $extension;
            $context['overrideSourceUrl'] = $sourceUrl;
            $mediaAssets[] = $this->baseDenormalizer->denormalize($post, MediaAsset::class, null, $context);
        }

        return $mediaAssets;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post;
    }
}
