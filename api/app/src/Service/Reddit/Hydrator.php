<?php

namespace App\Service\Reddit;

use App\Entity\ContentType;
use App\Entity\Post;
use App\Repository\ContentTypeRepository;
use App\Repository\TypeRepository;
use Exception;

class Hydrator
{
    // @TODO Move TYPE_ and CONTENT_ to respective Entity models once created.
    const TYPE_COMMENT = 't1';

    const TYPE_LINK = 't3';

    const CONTENT_TYPE_IMAGE = 'image';

    const CONTENT_TYPE_VIDEO = 'video';

    public function __construct(
        private readonly TypeRepository $typeRepository,
        private readonly ContentTypeRepository $contentTypeRepository
    ) {
    }

    /**
     * @param  array  $responseRawData
     *
     * @return Post
     * @throws Exception
     */
    public function hydratePostFromResponse(array $responseRawData): Post
    {
        if ($responseRawData['kind'] === 'Listing') {
            $responseRawData = $responseRawData['data']['children'][0];
        }

        if ($responseRawData['kind'] === self::TYPE_LINK) {
            return $this->hydrateLinkPostFromResponseData($responseRawData['data']);
        } elseif ($responseRawData['kind'] === self::TYPE_COMMENT) {
            return $this->initCommentPostFromRawData($responseRawData['data']);
        }

        throw new Exception(sprintf('Unexpected Post type %s: %s', $responseRawData['kind'], var_export($responseRawData, true)));
    }

    /**
     * Instantiate a new Post Entity and hydrate it using the data from the
     * provided Post Response Data array.
     *
     * @param  array  $responseData
     *
     * @return Post
     */
    private function hydrateLinkPostFromResponseData(array $responseData): Post
    {
        //@TODO: Create array validator using: https://symfony.com/doc/current/validation/raw_values.html

        $post = new Post();
        $post->setRedditId($responseData['id']);
        $post->setTitle($responseData['title']);
        $post->setScore((int)$responseData['score']);
        $post->setAuthor($responseData['author']);
        $post->setSubreddit($responseData['subreddit']);
        $post->setUrl($responseData['url']);
        $post->setCreatedAt(\DateTimeImmutable::createFromFormat('U', $responseData['created_utc']));

        $type = $this->typeRepository->getLinkType();
        $post->setType($type);

        $contentType = $this->getContentTypeFromResponseData($responseData);
        $post->setContentType($contentType);
        if ($contentType->getName() === ContentType::CONTENT_TYPE_TEXT) {
            $post->setAuthorText($responseData['selftext']);
        }

        return $post;
    }

    private function initCommentPostFromRawData(array $rawData): Post
    {
        return new Post();
    }

    /**
     * Determine and return the Content Type from the provided Post Response
     * Data.
     *
     * @param  array  $responseData
     *
     * @return ContentType
     */
    private function getContentTypeFromResponseData(array $responseData): ContentType
    {
        $contentType = null;
        if ($responseData['domain'] === 'i.imgur.com') {
            $contentType = $this->contentTypeRepository->getImageContentType();
        } else if (!empty($responseData['selftext']) && $responseData['is_self'] === true) {
            $contentType = $this->contentTypeRepository->getTextContentType();
        } else if ($this->isVideoContent($responseData) === true) {
            $contentType = $this->contentTypeRepository->getVideoContentType();
        } else if (!empty($responseData['gallery_data'])) {
            $contentType = $this->contentTypeRepository->getImageGalleryContentType();
        }

        return $contentType;
    }

    /**
     * Determine if the provided Post Response Data represents a Video Content
     * Type.
     *
     * @param  array  $responseData
     *
     * @return bool
     */
    private function isVideoContent(array $responseData): bool
    {
        $videoDomains = [
            'youtube.com',
            'youtu.be',
        ];

        if (in_array($responseData['domain'], $videoDomains)) {
            return true;
        }

        return false;
    }
}
