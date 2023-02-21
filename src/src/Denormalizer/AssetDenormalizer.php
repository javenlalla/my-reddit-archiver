<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Entity\Asset;
use App\Service\Reddit\Manager\Assets;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class AssetDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly Assets $assetsManager,
    ) {
    }

    /**
     * Retrieve the Asset file from the provided Source URL and persist a new
     * Asset Entity based on its downloaded data.
     *
     * @param  string  $data  Source URL of the target Asset.
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              filenameFormat: string,
     *              audioFilename: string,
     *              audioSourceUrl: string,
     *          }  $context  filenameFormat  Optional. Provide a custom format for the filename.
     *                                       Extension MUST be excluded.
     *                                       A placeholder for the ID hash MUST be included.
     *                                       Example: %s_thumb
     *
     * @return Asset|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): ?Asset
    {
        $sourceUrl = $data;

        $asset = new Asset();
        $asset->setSourceUrl($sourceUrl);

        $filenameFormat = null;
        if (!empty($context['filenameFormat'])) {
            $filenameFormat = $context['filenameFormat'];
        }

        if (!empty($context['audioFilename']) && !empty($context['audioSourceUrl'])) {
            $asset->setAudioFilename($context['audioFilename']);
            $asset->setAudioSourceUrl($context['audioSourceUrl']);
        }

        return $this->assetsManager->downloadAndProcessAsset($asset, $filenameFormat);
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Asset;
    }
}
