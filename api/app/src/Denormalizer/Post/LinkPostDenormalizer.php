<?php

namespace App\Denormalizer\Post;

use App\Denormalizer\MediaAssetsDenormalizer;
use App\Entity\ContentType;
use App\Entity\MediaAsset;
use App\Entity\Post;
use App\Helper\ContentTypeHelper;
use App\Helper\SanitizeHtmlHelper;
use App\Repository\TypeRepository;
use DateTimeImmutable;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class LinkPostDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly MediaAssetsDenormalizer $mediaAssetsDenormalizer,
        private readonly TypeRepository $typeRepository,
        private readonly ContentTypeHelper $contentTypeHelper,
        private readonly SanitizeHtmlHelper $sanitizeHtmlHelper
    ) {
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = null): bool
    {
        return is_array($data) && $type === Post::class;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Post
    {
        //@TODO: Create array validator using: https://symfony.com/doc/current/validation/raw_values.html
        $postData = $data;

        $post = new Post();
        $post->setRedditId($postData['id']);
        $post->setRedditPostId($post->getRedditId());
        // @TODO: Replace hard-coded URL here.
        $post->setRedditPostUrl('https://reddit.com' . $postData['permalink']);
        $post->setTitle($postData['title']);
        $post->setScore((int)$postData['score']);
        $post->setAuthor($postData['author']);
        $post->setSubreddit($postData['subreddit']);
        $post->setCreatedAt(DateTimeImmutable::createFromFormat('U', $postData['created_utc']));

        $type = $this->typeRepository->getLinkType();
        $post->setType($type);

        $contentType = $this->contentTypeHelper->getContentTypeFromPostData($postData);
        $post->setContentType($contentType);
        if ($contentType->getName() === ContentType::CONTENT_TYPE_TEXT) {
            $post->setAuthorText($postData['selftext']);
            $post->setAuthorTextRawHtml($postData['selftext_html']);
            $post->setAuthorTextHtml($this->sanitizeHtmlHelper->sanitizeHtml($postData['selftext_html']));
        }

        $post->setUrl($postData['url']);
        $mediaAssets = $this->mediaAssetsDenormalizer->denormalize($post, MediaAsset::class, null, ['postResponseData' => $postData]);

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
}
