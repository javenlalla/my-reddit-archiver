<?php

namespace App\Service\Reddit\Hydrator;

use App\Entity\ContentType;
use App\Entity\MediaAsset as MediaAssetEntity;
use App\Entity\Post;
use Exception;

class MediaAsset
{
    const REDDIT_VIDEO_AUDIO_URL_FORMAT = 'https://v.redd.it/%s/DASH_audio.mp4';

    /**
     * Initialize a new Media Asset and hydrate it from the provided Post entity.
     *
     * @param  Post  $post
     * @param  string  $overrideSourceUrl
     *
     * @return MediaAssetEntity
     */
    public function hydrateMediaAssetFromPost(Post $post, string $overrideSourceUrl = '', string $assetExtension = ''): MediaAssetEntity
    {
        $mediaAsset = new MediaAssetEntity();
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
     * Loop through the provided Response data for an Image Gallery and
     * instantiate a Media Asset entity for each associated gallery item.
     *
     * @param  Post  $post
     * @param  array  $responseData
     *
     * @return MediaAssetEntity[]
     */
    public function hydrateMediaAssetsFromPostMediaMetadata(Post $post, array $responseData): array
    {
        $mediaAssets = [];
        foreach ($responseData["media_metadata"] as $assetId => $mediaMetadata) {
            $sourceUrl = html_entity_decode($mediaMetadata['s']['u']);
            $mediaAssets[] = $this->hydrateMediaAssetFromPost($post, $sourceUrl, assetExtension: $this->extractExtensionFromMediaMetadata($mediaMetadata));
        }

        return $mediaAssets;
    }

    /**
     * Initialize a new Reddit Video Media Asset and hydrate it from the
     * provided Post entity.
     *
     * @param  Post  $post
     * @param  array  $responseData
     *
     * @return MediaAssetEntity
     */
    public function hydrateMediaAssetFromRedditVideoPost(Post $post, array $responseData): MediaAssetEntity
    {
        $mediaAsset = $this->hydrateMediaAssetFromPost($post, overrideSourceUrl: $responseData['media']['reddit_video']['fallback_url']);

        $videoId = str_replace('https://v.redd.it/', '', $post->getUrl());
        $audioUrl = sprintf(self::REDDIT_VIDEO_AUDIO_URL_FORMAT, $videoId);
        $mediaAsset->setAudioSourceUrl($audioUrl);

        return $mediaAsset;
    }

    /**
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
        }

        throw new Exception(sprintf('Unexpected media type in Media Metadata: %s', $mediaMetadata['m']));
    }
}
