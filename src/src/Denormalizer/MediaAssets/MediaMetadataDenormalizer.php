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
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        if (is_array($data) && $type === Asset::class) {
            $reversedData = array_reverse($data);
            $firstElement = array_pop($reversedData);

            return isset($firstElement['m']) && is_string($firstElement['m']);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false,
        ];
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

            $asset = $this->assetDenormalizer->denormalize($sourceUrl, Asset::class, null, $context);
            if ($asset instanceof Asset) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }
}
