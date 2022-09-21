<?php

namespace App\Denormalizer\MediaAssets;

use App\Entity\MediaAsset;
use App\Entity\Post;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class RedditVideoDenormalizer implements DenormalizerInterface
{
    const REDDIT_VIDEO_LOCAL_AUDIO_FILENAME_FORMAT = '%s_audio.mp4';

    const REDDIT_VIDEO_AUDIO_URL_FORMAT = 'https://v.redd.it/%s/DASH_audio.mp4';

    public function __construct(private readonly BaseDenormalizer $baseDenormalizer)
    {
    }

    /**
     * Analyze the provided Post and denormalize the associated Response data
     * for a Reddit Video into a Media Asset Entity.
     *
     * @param  Post  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              postResponseData: array,
     *     } $context   'postResponseData' contains the original API Response Data for this Post.
     *
     * @return MediaAsset
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): MediaAsset
    {
        $post = $data;
        $responseData = $context['postResponseData'];
        $context['overrideSourceUrl'] = $responseData['media']['reddit_video']['fallback_url'];
        $mediaAsset = $this->baseDenormalizer->denormalize($post, MediaAsset::class, null, $context);

        // If the Video Asset is not treated as a GIF on Reddit's side, assume
        // there is a separate Audio file to be retrieved.
        if ($responseData['media']['reddit_video']['is_gif'] === false) {
            $videoId = str_replace('https://v.redd.it/', '', $post->getUrl());
            $mediaAsset->setAudioFilename(sprintf(self::REDDIT_VIDEO_LOCAL_AUDIO_FILENAME_FORMAT, $videoId));

            $audioUrl = sprintf(self::REDDIT_VIDEO_AUDIO_URL_FORMAT, $videoId);
            $mediaAsset->setAudioSourceUrl($audioUrl);
        }

        return $mediaAsset;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post;
    }
}
