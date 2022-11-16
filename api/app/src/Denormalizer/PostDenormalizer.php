<?php

namespace App\Denormalizer;

use App\Entity\AuthorText;
use App\Entity\Award;
use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\MediaAsset;
use App\Entity\Post;
use App\Entity\PostAuthorText;
use App\Entity\PostAward;
use App\Entity\Thumbnail;
use App\Entity\Type;
use App\Helper\TypeHelper;
use App\Helper\SanitizeHtmlHelper;
use DateTimeImmutable;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class PostDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly MediaAssetsDenormalizer $mediaAssetsDenormalizer,
        private readonly AwardDenormalizer $awardDenormalizer,
        private readonly TypeHelper $typeHelper,
        private readonly SanitizeHtmlHelper $sanitizeHtmlHelper,
        private readonly ThumbnailDenormalizer $thumbnailDenormalizer,
    ) {
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = null): bool
    {
        return is_array($data) && $type === Post::class;
    }

    /**
     * @param  mixed  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *          content: Content,
     *     }  $context
     *
     * @return Post
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Post
    {
        $kindRedditId = $context['content']->getKind()->getRedditKindId();

        //@TODO: Create array validator using: https://symfony.com/doc/current/validation/raw_values.html
        $postData = $data;

        $post = new Post();
        $post->setRedditId($postData['id']);
        // @TODO: Replace hard-coded URL here.
        $post->setRedditPostUrl('https://reddit.com' . $postData['permalink']);
        $post->setTitle($postData['title']);
        $post->setScore((int)$postData['score']);
        $post->setAuthor($postData['author']);
        $post->setSubreddit($postData['subreddit']);
        $post->setCreatedAt(DateTimeImmutable::createFromFormat('U', $postData['created_utc']));

        if ($kindRedditId === Kind::KIND_LINK) {
            $type = $this->typeHelper->getContentTypeFromPostData($postData);
        } elseif ($kindRedditId === Kind::KIND_COMMENT) {
            $type = $this->typeHelper->getContentTypeFromPostData($context['parentPostData']['data']['children'][0]['data']);
        }
        $post->setType($type);
        $typeName = $type->getName();

        if ($typeName === Type::CONTENT_TYPE_TEXT && !empty($postData['selftext'])) {
            $authorText = new AuthorText();
            $authorText->setText($postData['selftext']);
            $authorText->setTextRawHtml($postData['selftext_html']);
            $authorText->setTextHtml($this->sanitizeHtmlHelper->sanitizeHtml($postData['selftext_html']));

            $postAuthorText = new PostAuthorText();
            $postAuthorText->setAuthorText($authorText);
            $postAuthorText->setCreatedAt($post->getCreatedAt());

            $post->addPostAuthorText($postAuthorText);
        }

        $post->setUrl($postData['url']);
        $mediaAssets = $this->mediaAssetsDenormalizer->denormalize($post, MediaAsset::class, null, ['postResponseData' => $postData, 'content' => $context['content']]);
        foreach ($mediaAssets as $mediaAsset) {
            $post->addMediaAsset($mediaAsset);
        }

        if (($typeName === Type::CONTENT_TYPE_GIF || $typeName === Type::CONTENT_TYPE_VIDEO)
            && !empty($mediaAssets)
        ) {
            $post->setUrl($mediaAssets[0]->getSourceUrl());
        }

        if (!empty($postData['all_awardings'])) {
            foreach ($postData['all_awardings'] as $awarding) {
                $award = $this->awardDenormalizer->denormalize($awarding, Award::class);

                $postAward = new PostAward();
                $postAward->setAward($award);
                $postAward->setCount((int) $awarding['count']);

                $post->addPostAward($postAward);
            }
        }

        if (!empty($postData['thumbnail'])) {
            $thumbnail = $this->thumbnailDenormalizer->denormalize($post, Thumbnail::class, null, ['sourceUrl' => $postData['thumbnail']]);
            $post->setThumbnail($thumbnail);
        }

        return $post;
    }
}
