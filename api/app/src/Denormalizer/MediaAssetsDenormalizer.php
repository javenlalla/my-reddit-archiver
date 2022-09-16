<?php

namespace App\Denormalizer;

use App\Entity\ContentType;
use App\Entity\MediaAsset;
use App\Entity\Post;
use Exception;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class MediaAssetsDenormalizer implements DenormalizerInterface
{
    const REDDIT_VIDEO_LOCAL_AUDIO_FILENAME_FORMAT = '%s_audio.mp4';

    const REDDIT_VIDEO_AUDIO_URL_FORMAT = 'https://v.redd.it/%s/DASH_audio.mp4';

    /**
     * Based on the provided Post, inspect its properties and denormalize its
     * associated Response Data in order to return a Media Asset Entity.
     *
     * 'postResponseData' contains the original API Response Data for this Post.
     * @param  Post  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              postResponseData: array,
     *          } $context
     *
     * @return MediaAsset[]
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): array
    {
        $post = $data;
        $responseData = $context['postResponseData'];
        $contentType = $data->getContentType();

        $mediaAssets = [];
        if ($contentType->getName() === ContentType::CONTENT_TYPE_IMAGE) {
            $mediaAssets[] = $this->hydrateMediaAssetFromPost($post);
        }

        if ($contentType->getName() === ContentType::CONTENT_TYPE_GIF) {
            $gifMp4SourceUrl = html_entity_decode($responseData['preview']['images'][0]['variants']['mp4']['source']['url']);

            $mediaAssets[] = $this->hydrateMediaAssetFromPost($post, $gifMp4SourceUrl);
        }

        if ($contentType->getName() === ContentType::CONTENT_TYPE_IMAGE_GALLERY || !empty($responseData['media_metadata'])) {
            $mediaAssets = $this->hydrateMediaAssetsFromPostMediaMetadata($post, $responseData);
        }

        if ($contentType->getName() === ContentType::CONTENT_TYPE_VIDEO && $responseData['is_video'] === true) {
            $mediaAssets[] = $this->hydrateMediaAssetFromRedditVideoPost($post, $responseData);
        }

        return $mediaAssets;
    }

    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
    {
        return is_array($data) && $type === MediaAsset::class;
    }

    /**
     * Initialize a new Media Asset and hydrate it from the provided Post entity.
     *
     * @param  Post  $post
     * @param  string  $overrideSourceUrl
     *
     * @return MediaAsset
     */
    private function hydrateMediaAssetFromPost(Post $post, string $overrideSourceUrl = '', string $assetExtension = ''): MediaAsset
    {
        $mediaAsset = new MediaAsset();
        $mediaAsset->setParentPost($post);

        $idHash = md5($post->getRedditId() . $overrideSourceUrl);

        $contentType = $post->getContentType()->getName();
        if (!empty($assetExtension)) {
            $mediaAsset->setFilename($idHash . '.' . $assetExtension);
        } else if ($contentType === ContentType::CONTENT_TYPE_IMAGE || $contentType === ContentType::CONTENT_TYPE_IMAGE_GALLERY) {
            $mediaAsset->setFilename($idHash . '.jpg');
        } else if ($contentType === ContentType::CONTENT_TYPE_GIF || $contentType === ContentType::CONTENT_TYPE_VIDEO) {
            $mediaAsset->setFilename($idHash . '.mp4');
        }

        $mediaAsset->setDirOne(substr($idHash, 0, 1));
        $mediaAsset->setDirTwo(substr($idHash, 1, 2));

        if (!empty($overrideSourceUrl)) {
            $mediaAsset->setSourceUrl($overrideSourceUrl);
        } else {
            $mediaAsset->setSourceUrl($post->getUrl());
        }

        return $mediaAsset;
    }

    /**
     * Initialize a new Reddit Video Media Asset and hydrate it from the
     * provided Post entity.
     *
     * @param  Post  $post
     * @param  array  $responseData
     *
     * @return MediaAsset
     */
    private function hydrateMediaAssetFromRedditVideoPost(Post $post, array $responseData): MediaAsset
    {
        $mediaAsset = $this->hydrateMediaAssetFromPost($post, overrideSourceUrl: $responseData['media']['reddit_video']['fallback_url']);

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
     * Loop through the provided Response data for an Image Gallery and
     * instantiate a Media Asset entity for each associated gallery item.
     *
     * @param  Post  $post
     * @param  array  $responseData
     *
     * @return MediaAsset[]
     */
    private function hydrateMediaAssetsFromPostMediaMetadata(Post $post, array $responseData): array
    {
        $mediaAssets = [];
        foreach ($responseData["media_metadata"] as $assetId => $mediaMetadata) {
            $extension = $this->extractExtensionFromMediaMetadata($mediaMetadata);
            if ($extension === 'mp4') {
                $sourceUrl = html_entity_decode($mediaMetadata['s']['mp4']);
            } else {
                $sourceUrl = html_entity_decode($mediaMetadata['s']['u']);
            }

            $mediaAssets[] = $this->hydrateMediaAssetFromPost($post, $sourceUrl, assetExtension: $extension);
        }

        return $mediaAssets;
    }

    /**
     * @TODO: Move this to a helper class.
     *
     * Read the 'm' property of the provided Media Metadata and return the
     * expected extension.
     *
     * @param  array  $mediaMetadata
     *
     * @return string|null
     * @throws Exception
     */
    private function extractExtensionFromMediaMetadata(array $mediaMetadata): ?string
    {
        switch ($mediaMetadata['m']) {
            case 'image/jpg':
                return 'jpg';

            case 'image/jpeg':
                return 'jpeg';

            case 'image/png':
                return 'png';

            case 'image/webp':
                return 'webp';

            case 'image/gif':
                return 'mp4';
        }

        throw new Exception(sprintf('Unexpected media type in Media Metadata: %s', $mediaMetadata['m']));
    }
}
