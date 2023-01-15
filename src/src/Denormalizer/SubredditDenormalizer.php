<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Entity\Asset;
use App\Entity\Subreddit;
use App\Helper\SanitizeHtmlHelper;
use App\Repository\SubredditRepository;
use App\Service\Reddit\Api;
use DateTimeImmutable;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class SubredditDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly SubredditRepository $subredditRepository,
        private readonly Api $redditApi,
        private readonly SanitizeHtmlHelper $sanitizeHtmlHelper,
        private readonly AssetDenormalizer $assetDenormalizer,
    ) {
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = null): bool
    {
        return is_array($data) && $type === Subreddit::class;
    }

    /**
     * @param  string  $data  Subreddit full Reddit ID. Ex: t5_2sdu8
     * @param  string  $type
     * @param  string|null  $format
     * @param  array  $context
     *
     * @return Subreddit
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Subreddit
    {
        $subredditId = $data;

        $subreddit = $this->subredditRepository->findOneBy(['redditId' => $subredditId]);
        if (($subreddit instanceof Subreddit) === false) {
            $subreddit = $this->initSubreddit($subredditId);
        }

        return $subreddit;
    }

    /**
     * Initialize a new Subreddit Entity persisted to the database by pulling
     * its information from the Reddit API.
     *
     * @param  string  $subredditId Full Subreddit Reddit ID. Ex: t5_2sdu8
     *
     * @return Subreddit
     */
    private function initSubreddit(string $subredditId): Subreddit
    {
        $subredditRawData = $this->redditApi->getRedditItemInfoById($subredditId);
        $subredditData = $subredditRawData['data']['children'][0]['data'];

        $subreddit = new Subreddit();
        $subreddit->setRedditId($subredditId);
        $subreddit->setName($subredditData['display_name']);
        $subreddit->setTitle($subredditData['title'] ?? null);
        $subreddit->setCreatedAt(DateTimeImmutable::createFromFormat('U', (string) $subredditData['created_utc']));

        $subreddit->setDescription($subredditData['description'] ?? null);
        if (!empty($subredditData['description_html'])) {
            $descriptionHtml = $subredditData['description_html'];

            $subreddit->setDescriptionRawHtml($descriptionHtml);
            $subreddit->setDescriptionHtml($this->sanitizeHtmlHelper->sanitizeHtml($descriptionHtml));
        }

        $subreddit->setPublicDescription($subredditData['public_description'] ?? null);
        if (!empty($subredditData['public_description_html'])) {
            $publicDescriptionHtml = $subredditData['public_description_html'];

            $subreddit->setPublicDescriptionRawHtml($publicDescriptionHtml);
            $subreddit->setPublicDescriptionHtml($this->sanitizeHtmlHelper->sanitizeHtml($publicDescriptionHtml));
        }

        if (!empty($subredditData['icon_img'])) {
            $iconImageAsset = $this->assetDenormalizer->denormalize($subredditData['icon_img'], Asset::class);
            $subreddit->setIconImageAsset($iconImageAsset);
        }

        if (!empty($subredditData['banner_background_image'])) {
            // The Banner Background Image URL has additional query parameters
            // that makes the URL inaccessible with a normal GET request. Remove
            // those parameters to get the base URL of the Asset.
            $bannerBackgroundImageUrlParts = explode('?', $subredditData['banner_background_image']);
            $bannerBackgroundAsset = $this->assetDenormalizer->denormalize($bannerBackgroundImageUrlParts[0], Asset::class);
            $subreddit->setBannerBackgroundImageAsset($bannerBackgroundAsset);
        }

        if (!empty($subredditData['banner_img'])) {
            $bannerImageAsset = $this->assetDenormalizer->denormalize($subredditData['banner_img'], Asset::class);
            $subreddit->setBannerImageAsset($bannerImageAsset);
        }

        $this->subredditRepository->add($subreddit, true);

        return $subreddit;
    }
}
