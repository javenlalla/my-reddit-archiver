<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Entity\Asset;
use App\Entity\AuthorText;
use App\Entity\Award;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\PostAuthorText;
use App\Entity\PostAward;
use App\Entity\Subreddit;
use App\Entity\Type;
use App\Helper\TypeHelper;
use App\Helper\SanitizeHtmlHelper;
use App\Repository\PostAwardRepository;
use App\Repository\PostRepository;
use DateTimeImmutable;
use Exception;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class PostDenormalizer implements DenormalizerInterface
{
    /**
     * Array of default image references within the `thumbnail` property on
     * Reddit's side to indicate a default image be used.
     */
    public const THUMBNAIL_DEFAULT_IMAGE_NAMES = [
        'image',
        'default',
        'nsfw',
        'self',
    ];

    private const THUMBNAIL_FILENAME_FORMAT = '%s_thumb';

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly PostAwardRepository $postAwardRepository,
        private readonly MediaAssetsDenormalizer $mediaAssetsDenormalizer,
        private readonly SubredditDenormalizer $subredditDenormalizer,
        private readonly AwardDenormalizer $awardDenormalizer,
        private readonly TypeHelper $typeHelper,
        private readonly SanitizeHtmlHelper $sanitizeHtmlHelper,
        private readonly AssetDenormalizer $assetDenormalizer,
    ) {
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = null): bool
    {
        return is_array($data) && $type === Post::class;
    }

    /**
     * @param  array  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *          kind: Kind,
     *          parentPostData: array,
     *     }  $context
     *
     * @return Post
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Post
    {
        $kindRedditId = $context['kind']->getRedditKindId();

        //@TODO: Create array validator using: https://symfony.com/doc/current/validation/raw_values.html
        $postData = $data;
        $redditId = $postData['id'];

        $post = $this->postRepository->findOneBy(['redditId' => $redditId]);
        if (empty($post)) {
            $post = $this->initNewPost($kindRedditId, $postData, $context);
        }

        return $this->updatePost($post, $postData);
    }

    /**
     * Initialize a new Post Entity and set associated properties that are not
     * expected to change on subsequent syncs.
     *
     * @param  string  $kindRedditId
     * @param  array  $postData
     *
     * @return Post
     * @throws Exception
     */
    private function initNewPost(string $kindRedditId, array $postData, array $context): Post
    {
        $post = new Post();

        $post->setRedditId($postData['id']);
        $post->setAuthor($postData['author']);
        $post->setCreatedAt(DateTimeImmutable::createFromFormat('U', (string) $postData['created_utc']));
        $post->setUrl($postData['url']);
        // @TODO: Replace hard-coded URL here.
        $post->setRedditPostUrl('https://www.reddit.com' . $postData['permalink']);

        $subreddit = $this->subredditDenormalizer->denormalize($postData['subreddit_id'], Subreddit::class);
        $post->setSubreddit($subreddit);

        if ($kindRedditId === Kind::KIND_LINK) {
            $type = $this->typeHelper->getContentTypeFromPostData($postData);
        } elseif ($kindRedditId === Kind::KIND_COMMENT) {
            $type = $this->typeHelper->getContentTypeFromPostData($context['parentPostData']['data']['children'][0]['data']);
        }
        $post->setType($type);

        $post = $this->processAssets($post, $type, $postData);

        return $post;
    }

    /**
     * Update the properties of the provided Post that are expected to or have
     * the potential to change on subsequent syncs.
     *
     * @param  Post  $post
     * @param  array  $postData
     *
     * @return Post
     */
    private function updatePost(Post $post, array $postData): Post
    {
        $post->setTitle($postData['title']);
        $post->setScore((int)$postData['score']);

        $post->setIsArchived($postData['archived']);
        $post->setFlairText($postData['link_flair_text'] ?? null);

        $typeName = $post->getType()->getName();

        if ($typeName === Type::CONTENT_TYPE_TEXT && !empty($postData['selftext'])) {
            $text = $postData['selftext'];

            $postAuthorText = $post->getPostAuthorTextByText($text);
            if (empty($postAuthorText)) {
                // To prevent duplicate text records for the same Post, create
                // a new Post Author Text only if one does not already exist with
                // target text.
                $authorText = new AuthorText();
                $authorText->setText($text);
                $authorText->setTextRawHtml($postData['selftext_html']);
                $authorText->setTextHtml($this->sanitizeHtmlHelper->sanitizeHtml($postData['selftext_html']));

                $postAuthorText = new PostAuthorText();
                $postAuthorText->setAuthorText($authorText);

                // Prioritize the updated date over the created date when the
                // Post has been edited.
                $createdDate = $post->getCreatedAt();
                if ($postData['edited'] !== false && is_numeric($postData['edited'])) {
                    $createdDate = DateTimeImmutable::createFromFormat('U', (string) $postData['edited']);
                }
                $postAuthorText->setCreatedAt($createdDate);

                $post->addPostAuthorText($postAuthorText);
            }
        }

        if (!empty($postData['all_awardings'])) {
            foreach ($postData['all_awardings'] as $awarding) {
                $award = $this->awardDenormalizer->denormalize($awarding, Award::class);

                $postAward = $this->postAwardRepository->findOneBy(['post' => $post, 'award' => $award]);
                if (empty($postAward)) {
                    $postAward = new PostAward();
                    $postAward->setAward($award);
                }

                $postAward->setCount((int) $awarding['count']);
                $post->addPostAward($postAward);
            }
        }

        return $post;
    }

    /**
     * Download and instantiate new Asset Entities for any Media and/or
     * Thumbnail Assets the provided Post may have associated to it.
     *
     * @param  Post  $post
     * @param  Type  $type
     * @param  array  $postData
     *
     * @return Post
     */
    private function processAssets(Post $post, Type $type, array $postData): Post
    {
        $mediasMetadata = [];
        if (!empty($postData['media_metadata'])) {
            $mediasMetadata = $postData['media_metadata'];
        }

        $isVideo = false;
        $videoSourceUrl = null;
        if (!empty($postData['is_video'])
            && $postData['is_video'] === true
            && !empty($postData['media']['reddit_video']['fallback_url'])
        ) {
            $isVideo = true;
            $videoSourceUrl = $postData['media']['reddit_video']['fallback_url'];
        }

        $isGif = false;
        if (!empty($postData['media']['reddit_video']['is_gif']) && $postData['media']['reddit_video']['is_gif'] === true) {
            $isGif = true;
        }

        $gifSourceUrl = null;
        if (!empty($postData['preview']['images'][0]['variants']['mp4']['source']['url'])) {
            $gifSourceUrl = html_entity_decode($postData['preview']['images'][0]['variants']['mp4']['source']['url']);
        }

        $context = [
            'mediasMetadata' => $mediasMetadata,
            'isVideo' => $isVideo,
            'videoSourceUrl' => $videoSourceUrl,
            'isGif' => $isGif,
            'gifSourceUrl' => $gifSourceUrl,
            'postType' => $type,
        ];

        $sourceUrl = $postData['url'];

        $mediaAssets = $this->mediaAssetsDenormalizer->denormalize($sourceUrl, Asset::class, null, $context);
        foreach ($mediaAssets as $mediaAsset) {
            $post->addMediaAsset($mediaAsset);
        }

        // Process the Post's Thumbnail, if any.
        // The `height` check is included to avoid false positives such as when
        // `thumbnail` = "self" in the case of a Text Post (for example).
        if (!empty($postData['thumbnail'])
            && !in_array($postData['thumbnail'], self::THUMBNAIL_DEFAULT_IMAGE_NAMES)
            && !empty($postData['thumbnail_height'])
        ) {
            $thumbnailAsset = $this->assetDenormalizer->denormalize($postData['thumbnail'], Asset::class, null, ['filenameFormat' => self::THUMBNAIL_FILENAME_FORMAT]);
            $post->setThumbnailAsset($thumbnailAsset);
        }

        return $post;
    }
}
