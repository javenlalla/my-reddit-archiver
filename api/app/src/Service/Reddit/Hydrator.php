<?php

namespace App\Service\Reddit;

use App\Denormalizer\MediaAssetsDenormalizer;
use App\Entity\ContentType;
use App\Entity\Post;
use App\Repository\ContentTypeRepository;
use App\Repository\TypeRepository;
use Exception;

class Hydrator
{
    const TYPE_COMMENT = 't1';

    const TYPE_LINK = 't3';

    public function __construct(
        private readonly MediaAssetsDenormalizer $mediaAssetsDenormalizer,
        private readonly TypeRepository $typeRepository,
        private readonly ContentTypeRepository $contentTypeRepository
    ) {
    }

    /**
     * Instantiate and hydrate Post Entity based on the provided Response data.
     *
     * @param  array  $responseRawData
     * @param  array  $parentResponseRawData
     *
     * @return Post
     * @throws Exception
     */
    public function hydratePostFromResponse(array $responseRawData, array $parentResponseRawData = []): Post
    {
        if ($responseRawData['kind'] === 'Listing') {
            $responseRawData = $responseRawData['data']['children'][0];
        }

        if ($responseRawData['kind'] === self::TYPE_LINK) {
            return $this->hydrateLinkPostFromResponseData($responseRawData['data']);
        } elseif ($responseRawData['kind'] === self::TYPE_COMMENT) {
            return $this->hydrateCommentPostFromResponseData($responseRawData['data'], $parentResponseRawData['data']['children'][0]['data']);
        }

        throw new Exception(sprintf('Unexpected Post type %s: %s', $responseRawData['kind'], var_export($responseRawData, true)));
    }

    /**
     * Instantiate a new Link Post Entity and hydrate it using the data from the
     * provided Post Response Data array.
     *
     * @param  array  $responseData
     *
     * @return Post
     * @throws Exception
     */
    private function hydrateLinkPostFromResponseData(array $responseData): Post
    {
        //@TODO: Create array validator using: https://symfony.com/doc/current/validation/raw_values.html

        $post = new Post();
        $post->setRedditId($responseData['id']);
        $post->setRedditPostId($post->getRedditId());
        // @TODO: Replace hard-coded URL here.
        $post->setRedditPostUrl('https://reddit.com' . $responseData['permalink']);
        $post->setTitle($responseData['title']);
        $post->setScore((int)$responseData['score']);
        $post->setAuthor($responseData['author']);
        $post->setSubreddit($responseData['subreddit']);
        $post->setCreatedAt(\DateTimeImmutable::createFromFormat('U', $responseData['created_utc']));

        $type = $this->typeRepository->getLinkType();
        $post->setType($type);

        $contentType = $this->getContentTypeFromResponseData($responseData);
        $post->setContentType($contentType);
        if ($contentType->getName() === ContentType::CONTENT_TYPE_TEXT) {
            $post->setAuthorText($responseData['selftext']);
            $post->setAuthorTextRawHtml($responseData['selftext_html']);
            $post->setAuthorTextHtml($this->sanitizeHtml($responseData['selftext_html']));
        }

        $post->setUrl($responseData['url']);
        $mediaAssets = $this->mediaAssetsDenormalizer->denormalize($post, \App\Entity\MediaAsset::class, null, ['postResponseData' => $responseData]);

        foreach ($mediaAssets as $mediaAsset) {
            $post->addMediaAsset($mediaAsset);
        }

        if (($contentType->getName() === ContentType::CONTENT_TYPE_GIF || $contentType->getName() === ContentType::CONTENT_TYPE_VIDEO)
            && !empty($mediaAssets)
        ) {
            $post->setUrl($mediaAssets[0]->getSourceUrl());
        }

        return $post;
    }

    /**
     * Instantiate a new Comment Post Entity and hydrate it using the data from the
     * provided Post Response Data array.
     *
     * @param  array  $responseData
     * @param  array  $parentResponseData
     *
     * @return Post
     */
    private function hydrateCommentPostFromResponseData(array $responseData, array $parentResponseData): Post
    {
        //@TODO: Create array validator using: https://symfony.com/doc/current/validation/raw_values.html

        $post = new Post();
        $post->setRedditId($responseData['id']);
        $post->setTitle($parentResponseData['title']);
        $post->setScore((int)$responseData['score']);
        $post->setAuthor($responseData['author']);
        $post->setSubreddit($responseData['subreddit']);
        $post->setUrl($parentResponseData['url']);
        $post->setCreatedAt(\DateTimeImmutable::createFromFormat('U', $responseData['created_utc']));

        $type = $this->typeRepository->getCommentType();
        $post->setType($type);

        $contentType = $this->contentTypeRepository->getTextContentType();
        $post->setContentType($contentType);
        $post->setAuthorText($responseData['body']);
        $post->setAuthorTextRawHtml($responseData['body_html']);
        $post->setAuthorTextHtml($this->sanitizeHtml($responseData['body_html']));

        if (!empty($parentResponseData)) {
            $post->setRedditPostId($parentResponseData['id']);
            $post->setRedditPostUrl('https://reddit.com/' . $parentResponseData['permalink']);
        } else {
            $post->setRedditPostId($post->getRedditId());
            $post->setRedditPostUrl('https://reddit.com/' . $responseData['permalink']);
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
     * @throws Exception
     */
    private function getContentTypeFromResponseData(array $responseData): ContentType
    {
        $contentType = $this->getContentTypeFromDomainAndUrl($responseData);
        if ($contentType instanceof ContentType) {
            return $contentType;
        }

        if ($responseData['domain'] === 'i.imgur.com'
            || ($responseData['domain'] === 'i.redd.it' && $responseData['is_reddit_media_domain'] === true)
        ) {
            return $this->contentTypeRepository->getImageContentType();
        } else if (!empty($responseData['selftext']) && $responseData['is_self'] === true) {
            return $this->contentTypeRepository->getTextContentType();
        } else if ($this->isVideoContent($responseData) === true) {
            return $this->contentTypeRepository->getVideoContentType();
        } else if (!empty($responseData['gallery_data'])) {
            return $this->contentTypeRepository->getImageGalleryContentType();
        } else if (!empty($responseData['preview']['images'][0]['variants']['gif']) && !empty($responseData['preview']['images'][0]['variants']['mp4'])) {
            return $this->contentTypeRepository->getGifContentType();
        } else if (empty($responseData['selftext']) && $responseData['is_self'] === true) {
            return $this->contentTypeRepository->getTextContentType();
        } else if ($this->postIsExternalLink($responseData) === true) {
            return $this->contentTypeRepository->getExternalLinkContentType();
        }

        throw new Exception(sprintf('Unable to determine Content Type for response data: %s', var_export($responseData, true)));
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
     * Perform basic sanitization on the raw HTML of a Post or Comment to parse
     * Reddit's HTML and clean up extraneous tags.
     *
     * @param  string|null  $html
     *
     * @return string
     */
    private function sanitizeHtml(?string $html): string
    {
        $html = trim($html);
        if ( empty($html) ) {
            return '';
        }

        // Run a double decode through Reddit's Markdown-converted HTML.
        $html = html_entity_decode($html);
        $html = html_entity_decode($html);

        // Clean up unneeded tags and strings.
        $stringsToRemove = [
            '<!-- SC_OFF -->',
            '<!-- SC_ON -->',
            '\n',
        ];
        $html = str_replace($stringsToRemove, '', $html);

        return $html;
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
