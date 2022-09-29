<?php

namespace App\Helper;

use App\Entity\ContentType;
use App\Repository\ContentTypeRepository;
use Exception;

class ContentTypeHelper
{
    public function __construct(
        private readonly ContentTypeRepository $contentTypeRepository
    ) {
    }

    /**
     * Determine and return the Content Type from the provided Post Response
     * Data.
     *
     * @param  array  $postData
     *
     * @return ContentType
     * @throws Exception
     */
    public function getContentTypeFromPostData(array $postData): ContentType
    {
        $contentType = $this->getContentTypeFromDomainAndUrl($postData);
        if ($contentType instanceof ContentType) {
            return $contentType;
        }

        if ($postData['domain'] === 'i.imgur.com'
            || ($postData['domain'] === 'i.redd.it' && $postData['is_reddit_media_domain'] === true)
        ) {
            return $this->contentTypeRepository->getImageContentType();
        } else if (!empty($postData['selftext']) && $postData['is_self'] === true) {
            return $this->contentTypeRepository->getTextContentType();
        } else if ($this->isVideoContent($postData) === true) {
            return $this->contentTypeRepository->getVideoContentType();
        } else if (!empty($postData['gallery_data'])) {
            return $this->contentTypeRepository->getImageGalleryContentType();
        } else if (!empty($postData['preview']['images'][0]['variants']['gif']) && !empty($postData['preview']['images'][0]['variants']['mp4'])) {
            return $this->contentTypeRepository->getGifContentType();
        } else if (empty($postData['selftext']) && $postData['is_self'] === true) {
            return $this->contentTypeRepository->getTextContentType();
        } else if ($this->postIsExternalLink($postData) === true) {
            return $this->contentTypeRepository->getExternalLinkContentType();
        }

        throw new Exception(sprintf('Unable to determine Content Type for response data: %s', var_export($postData, true)));
    }

    /**
     * Attempt to retrieve the Content Type for the provided Response Data based
     * on its domain and URL properties.
     *
     * @param  array  $responseData
     *
     * @return ContentType|null
     */
    private function getContentTypeFromDomainAndUrl(array $responseData): ?ContentType
    {
        $domain = $responseData['domain'];
        if (in_array($domain, ['i.imgur.com', 'i.redd.it'])) {
            $extensionSeparatorPos = strrpos($responseData['url'], '.');
            $extensionPosition = $extensionSeparatorPos + 1;

            $extension = substr($responseData['url'], $extensionPosition, strlen($responseData['url']) - $extensionPosition);

            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'webp':
                    return $this->contentTypeRepository->getImageContentType();

                case 'gif':
                    return $this->contentTypeRepository->getGifContentType();
            }
        }

        return null;
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
        if ($responseData['is_video'] === true) {
            return true;
        }

        $videoDomains = [
            'youtube.com',
            'youtu.be',
        ];

        if (in_array($responseData['domain'], $videoDomains)) {
            return true;
        }

        return false;
    }

    /**
     * Verify if a Post links to an external (anything not *.reddit.com) site
     * based on the provided Response Data.
     *
     * @param  array  $responseData
     *
     * @return bool
     */
    private function postIsExternalLink(array $responseData): bool
    {
        $domainContainsReddit = strrpos($responseData['domain'], 'reddit');
        if ($domainContainsReddit !== false) {
            return false;
        }

        if ($responseData['is_self'] === true) {
            return false;
        }

        return true;
    }
}