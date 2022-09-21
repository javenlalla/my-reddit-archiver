<?php

namespace App\Denormalizer\MediaAssets;

use App\Entity\ContentType;
use App\Entity\MediaAsset;
use App\Entity\Post;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class BaseDenormalizer implements DenormalizerInterface
{
    /**
     * Analyze the provided Post and denormalize its associated data into a
     * Media Asset Entity.
     *
     * @param  Post  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              postResponseData: array,
     *              overrideSourceUrl: string,
     *              assetExtension: string,
     *          } $context  'postResponseData' contains the original API Response Data for this Post.
     *
     * @return MediaAsset
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): MediaAsset
    {
        $post = $data;
        $responseData = $context['postResponseData'];
        $overrideSourceUrl = $context['overrideSourceUrl'] ?? '';
        $assetExtension = $context['assetExtension'] ?? '';

        $mediaAsset = new MediaAsset();
        $mediaAsset->setParentPost($post);


        $contentType = $post->getContentType()->getName();
        if ($contentType === ContentType::CONTENT_TYPE_GIF) {
            $overrideSourceUrl = html_entity_decode($responseData['preview']['images'][0]['variants']['mp4']['source']['url']);
        }

        $idHash = md5($post->getRedditId() . $overrideSourceUrl);

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
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post;
    }
}
