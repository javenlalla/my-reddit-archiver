<?php
declare(strict_types=1);

namespace App\Denormalizer\MediaAssets;

use App\Denormalizer\AssetDenormalizer;
use App\Entity\Asset;
use App\Entity\Post;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RedditVideoDenormalizer implements DenormalizerInterface
{
    const REDDIT_VIDEO_LOCAL_AUDIO_FILENAME_FORMAT = '%s_audio.mp4';

    const REDDIT_VIDEO_AUDIO_URL_FORMAT = 'https://v.redd.it/%s/DASH_audio.mp4';

    public function __construct(
        private readonly AssetDenormalizer $assetDenormalizer,
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post;
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
     * Analyze the provided Post and denormalize the associated Response data
     * for a Reddit Video into a Media Asset Entity.
     *
     * @param  string  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              isGif: bool,
     *              videoSourceUrl: string,
     *     }  $context  'postResponseData' contains the original API Response Data for this Post.
     *
     * @return Asset|null
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): ?Asset
    {
        $sourceUrl = $data;
        $videoSourceUrl = $context['videoSourceUrl'];
        $isGif = $context['isGif'];
        $context['audioFilename'] = null;
        $context['audioSourceUrl'] = null;

        // If the Video Asset is not treated as a GIF on Reddit's side, assume
        // there is a separate Audio file to be retrieved.
        if ($isGif === false) {
            $videoId = str_replace('https://v.redd.it/', '', $sourceUrl);
            $context['audioFilename'] = sprintf(self::REDDIT_VIDEO_LOCAL_AUDIO_FILENAME_FORMAT, $videoId);

            $audioUrl = sprintf(self::REDDIT_VIDEO_AUDIO_URL_FORMAT, $videoId);
            if ($this->audioUrlReturnsSuccessful($audioUrl) === true) {
                $context['audioSourceUrl'] = $audioUrl;
            }
        }

        return $this->assetDenormalizer->denormalize($videoSourceUrl, Asset::class, null, $context);
    }

    /**
     * Execute a GET call to the provided URL and ensure a 200 is returned which
     * would indicate the Audio asset exists and is reachable.
     *
     * @param  string  $audioUrl
     *
     * @return bool
     * @throws TransportExceptionInterface
     */
    private function audioUrlReturnsSuccessful(string $audioUrl): bool
    {
        $responseStatusCode = $this->httpClient->request('GET', $audioUrl)->getStatusCode();
        if ($responseStatusCode === 200) {
            return true;
        }

        return false;
    }
}
