<?php
declare(strict_types=1);

namespace App\Denormalizer\MediaAssets;

use App\Denormalizer\AssetDenormalizer;
use App\Entity\Asset;
use App\Service\Reddit\Manager\Assets;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class MediaMetadataDenormalizer implements DenormalizerInterface
{
    public function __construct(private readonly AssetDenormalizer $assetDenormalizer, private readonly Assets $assetsManager)
    {
    }

    /**
     * Analyze the provided array of Media Metadata and denormalize the
     * metadata for an Image Gallery or Media Metadata array into Asset
     * Entities.
     *
     * @param  array  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array  $context
     *
     * @return Asset[]
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): array
    {
        $mediasMetadata = $data;

        $assets = [];
        foreach ($mediasMetadata as $assetId => $mediaMetadata) {
            $extension = $this->assetsManager->getAssetExtensionFromContentTypeValue($mediaMetadata['m']);
            if ($extension === 'mp4') {
                $sourceUrl = html_entity_decode($mediaMetadata['s']['mp4']);
            } else {
                $sourceUrl = html_entity_decode($mediaMetadata['s']['u']);
            }

            $assets[] = $this->assetDenormalizer->denormalize($sourceUrl, Asset::class, null, $context);
        }

        return $assets;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return is_array($data);
    }
}
