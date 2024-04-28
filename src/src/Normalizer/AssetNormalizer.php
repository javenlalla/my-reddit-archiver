<?php
declare(strict_types=1);

namespace App\Normalizer;

use App\Entity\Asset;
use App\Service\Reddit\Manager\Assets;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AssetNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly Assets $assetsManager,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof Asset;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Asset::class => true,
        ];
    }

    /**
     * @param  Asset  $object
     * @param  string|null  $format
     * @param  array  $context
     *
     * @return array
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array
    {
        $asset = $object;

        $normalizedData = [
            'id' => $asset->getId(),
            'path' => $this->assetsManager->getAssetPath($asset),
        ];

        return $normalizedData;
    }
}
