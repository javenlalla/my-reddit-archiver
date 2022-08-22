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
     *
     * @return MediaAssetEntity
     */
    public function hydrateMediaAssetFromPost(Post $post): MediaAssetEntity
    {
        $mediaAsset = new MediaAssetEntity();
        $mediaAsset->setParentPost($post);

        $idHash = md5($post->getRedditId());

        $contentType = $post->getContentType()->getName();
        if ($contentType === ContentType::CONTENT_TYPE_IMAGE) {
            $mediaAsset->setFilename($idHash . '.jpg');
        } else if ($contentType === ContentType::CONTENT_TYPE_GIF) {
            $mediaAsset->setFilename($idHash . '.mp4');
        }

        $mediaAsset->setSourceUrl($post->getUrl());
        $mediaAsset->setDirOne(substr($idHash, 0, 1));
        $mediaAsset->setDirTwo(substr($idHash, 1, 2));

        return $mediaAsset;
    }
}
