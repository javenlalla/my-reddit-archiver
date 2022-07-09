<?php

namespace App\Service\Reddit;

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
     * @return \App\Entity\Post
     * @throws Exception
     */
    public function hydratePostFromResponse(array $responseRawData): \App\Entity\Post
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

    // private function initFromRawData(array $rawData)
    // {
    //     //@TODO: Create array validator using: https://symfony.com/doc/current/validation/raw_values.html
    //     if ($rawData['kind'] === self::TYPE_LINK) {
    //         $this->initLinkPostFromRawData($rawData);
    //     } elseif ($rawData['kind'] === self::TYPE_COMMENT) {
    //         $this->initCommentPostFromRawData($rawData);
    //     } else {
    //         throw new \Exception(sprintf('Unexpected Post type %s: %s', $rawData['kind'], var_export($rawData, true)));
    //     }
    // }

    private function hydrateLinkPostFromResponseData(array $responseData): \App\Entity\Post
    {
        $post = new \App\Entity\Post();
        $post->setRedditId($responseData['id']);
        $post->setTitle($responseData['title']);
        $post->setScore((int)$responseData['score']);
        $post->setUrl($responseData['url']);

        $type = $this->typeRepository->getLinkType();
        $post->setType($type);

        if ($responseData['domain'] === 'i.imgur.com' || !empty($responseData['preview']['images'])) {
            $contentType = $this->contentTypeRepository->getImageContentType();
            $post->setContentType($contentType);
        }

        return $post;
    }

    private function initCommentPostFromRawData(array $rawData): \App\Entity\Post
    {
        return new \App\Entity\Post();
    }
}
