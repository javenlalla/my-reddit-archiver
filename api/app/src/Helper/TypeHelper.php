<?php

namespace App\Helper;

use App\Entity\Type;
use App\Repository\TypeRepository;
use Exception;

class TypeHelper
{
    public function __construct(
        private readonly TypeRepository $typeRepository
    ) {
    }

    /**
     * Determine and return the Type from the provided Post Response data.
     *
     * @param  array  $postData
     *
     * @return Type
     * @throws Exception
     */
    public function getContentTypeFromPostData(array $postData): Type
    {
        $type = $this->getTypeFromDomainAndUrl($postData);
        if ($type instanceof Type) {
            return $type;
        }

        if ($postData['domain'] === 'i.imgur.com'
            || ($postData['domain'] === 'i.redd.it' && $postData['is_reddit_media_domain'] === true)
        ) {
            return $this->typeRepository->getImageType();
        } else if (!empty($postData['selftext']) && $postData['is_self'] === true) {
            return $this->typeRepository->getTextType();
        } else if ($this->isVideoType($postData) === true) {
            return $this->typeRepository->getVideoType();
        } else if (!empty($postData['gallery_data'])) {
            return $this->typeRepository->getImageGalleryType();
        } else if (!empty($postData['preview']['images'][0]['variants']['gif']) && !empty($postData['preview']['images'][0]['variants']['mp4'])) {
            return $this->typeRepository->getGifType();
        } else if (empty($postData['selftext']) && $postData['is_self'] === true) {
            return $this->typeRepository->getTextType();
        } else if ($this->postIsExternalLink($postData) === true) {
            return $this->typeRepository->getExternalLinkType();
        }

        throw new Exception(sprintf('Unable to determine Type for response data: %s', var_export($postData, true)));
    }

    /**
     * Attempt to retrieve the Type for the provided Response Data based on its
     * domain and URL properties.
     *
     * @param  array  $responseData
     *
     * @return Type|null
     */
    private function getTypeFromDomainAndUrl(array $responseData): ?Type
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
                    return $this->typeRepository->getImageType();

                case 'gif':
                    return $this->typeRepository->getGifType();
            }
        }

        return null;
    }

    /**
     * Determine if the provided Post Response Data represents a Video Type.
     *
     * @param  array  $responseData
     *
     * @return bool
     */
    private function isVideoType(array $responseData): bool
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