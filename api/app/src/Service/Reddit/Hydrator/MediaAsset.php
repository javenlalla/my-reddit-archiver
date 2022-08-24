<?php

namespace App\Service\Reddit\Hydrator;

use App\Entity\ContentType;
use App\Entity\MediaAsset as MediaAssetEntity;
use App\Entity\Post;

class MediaAsset
{
    /**
     * Initialize a new Media Asset and hydrate it from the provided Post entity.
     *
     * @param  Post  $post
     * @param  string  $overrideSourceUrl
     *
     * @return MediaAssetEntity
     */
    public function hydrateMediaAssetFromPost(Post $post, string $overrideSourceUrl = ''): MediaAssetEntity
    {
        $mediaAsset = new MediaAssetEntity();
        $mediaAsset->setParentPost($post);

        $idHash = md5($post->getRedditId() . $overrideSourceUrl);

        $contentType = $post->getContentType()->getName();
        if ($contentType === ContentType::CONTENT_TYPE_IMAGE || $contentType === ContentType::CONTENT_TYPE_IMAGE_GALLERY) {
            // @TODO: Add proper extension detection for .png, .jpg/.jpeg, and .webp.
            $mediaAsset->setFilename($idHash . '.jpg');
        } else if ($contentType === ContentType::CONTENT_TYPE_GIF) {
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
    public function hydrateGalleryMediaAssetsFromPost(Post $post, array $responseData): array
    {
        $mediaAssets = [];
        foreach ($responseData["media_metadata"] as $assetId => $assetMetadata) {
            $sourceUrl = html_entity_decode($assetMetadata['s']['u']);
            $mediaAssets[] = $this->hydrateMediaAssetFromPost($post, $sourceUrl);
        }

        return $mediaAssets;
    }
}
