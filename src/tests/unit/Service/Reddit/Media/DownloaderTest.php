<?php
declare(strict_types=1);

namespace App\Tests\unit\Service\Reddit\Media;

use App\Entity\Asset;
use App\Entity\Content;
use App\Entity\Kind;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager;
use Doctrine\ORM\EntityManager;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class DownloaderTest extends KernelTestCase
{
    const BASE_PATH_FORMAT = '/var/www/mra/public/r-media/%s/%s/';

    const SUBREDDIT_ICON_IMAGE_ASSET = [
        'sourceUrl' => 'https://b.thumbs.redditmedia.com/VgpGZUKuANeo3HOjv6t-lZqF31zNAFqTTfdP6q_PYQk.png',
        'filename' => 'aa7d838e7469d7d325d271459d452ae7.png',
        'dirOne' => 'a',
        'dirTwo' => 'a7',
        'filesize' => 85865,
    ];

    const SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET = [
        'sourceUrl' => 'https://styles.redditmedia.com/t5_2sdu8/styles/bannerBackgroundImage_vkavz41901m01.png',
        'filename' => '963ee12348e5b7300dbff2db9c9ec2f3.png',
        'dirOne' => '9',
        'dirTwo' => '63',
        'filesize' => 2929636,
    ];

    const SUBREDDIT_BANNER_IMAGE_ASSET = [
        'sourceUrl' => 'https://b.thumbs.redditmedia.com/wrAra1971jR8b6hTiLEixTqMYyY0Jc5oPpGlky8xZrQ.png',
        'filename' => '52cb64200009ba810d0af34c324ed678.png',
        'dirOne' => '5',
        'dirTwo' => '2c',
        'filesize' => 1034844,
    ];

    const AWARD_SILVER_ICON_ASSET = [
        'sourceUrl' => 'https://www.redditstatic.com/gold/awards/icon/silver_512.png',
        'filename' => '67dc13e06c2ede9b414970cef60e7e50.png',
        'dirOne' => '6',
        'dirTwo' => '7d',
        'filesize' => 40381,
    ];

    const AWARD_FACEPALM_ICON_ASSET = [
        'sourceUrl' => 'https://i.redd.it/award_images/t5_22cerq/ey2iodron2s41_Facepalm.png',
        'filename' => 'e7b2abf9441645936879161ed466faeb.png',
        'dirOne' => 'e',
        'dirTwo' => '7b',
        'filesize' => 166418,
    ];

    const HTML_VIDEO_ASSET = [
        'sourceUrl' => 'https://i.imgur.com/nL4zkco.mp4',
        'filename' => '5ad577eee5d00689e04643f6898c2d4e.mp4',
        'dirOne' => '5',
        'dirTwo' => 'ad',
        'filesize' => 291523,
    ];

    const ENCODED_URL_ASSET = [
        'sourceUrl' => 'https://v.redd.it/zr04l9pxhq6b1/DASH_240.mp4?source=fallback',
        'filename' => '32d91f2057667044e23c97071ed54b3d.mp4',
        'dirOne' => '3',
        'dirTwo' => '2d',
        'filesize' => 1158978,
    ];

    private Manager $manager;

    private EntityManager $entityManager;

    private Manager\Assets $assetsManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->assetsManager = $container->get(Manager\Assets::class);

        $this->cleanupAssets();
    }

    /**
     * The following Post throws an error when attempting to sync due to the Asset
     * URL having encoded parameter values:
     * https://external-preview.redd.it/NTMyaGZ0anhocTZiMZvlzPEo-APD7rAOWZWDJKO4z89bH6yzQ0QcAO59Zj9m.png?width=140&amp;height=77&amp;crop=140:77,smart&amp;format=jpg&amp;v=enabled&amp;lthumb=true&amp;s=b176dbf2eb5c66e278e1695f2117678dee1d8275
     *
     * Implement decoding logic and verify the Post can be synced correctly.
     *
     * https://www.reddit.com/r/familyguy/comments/14cerh3/punish_your_toilet/
     * @return void
     */
    public function testEncodedAssetUrl()
    {
        $assetPath = sprintf(self::BASE_PATH_FORMAT, self::ENCODED_URL_ASSET['dirOne'], self::ENCODED_URL_ASSET['dirTwo']) . self::ENCODED_URL_ASSET['filename'];
        $this->assertFileDoesNotExist($assetPath);

        $context = new Context('DownloaderTest:testAssetUrlError');
        $redditId = 't3_14cerh3';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);

        $post = $content->getPost();
        $this->assertEquals('https://v.redd.it/zr04l9pxhq6b1', $post->getUrl());

        $mediaAsset = $post->getMediaAssets()->first();
        $this->assertFalse($mediaAsset->isDownloaded());

        $mediaAsset = $this->assetsManager->downloadAsset($mediaAsset);
        $this->assertTrue($mediaAsset->isDownloaded());

        $this->assertInstanceOf(Asset::class, $mediaAsset);
        $this->assertEquals(self::ENCODED_URL_ASSET['sourceUrl'], $mediaAsset->getSourceUrl());
        $this->assertEquals(self::ENCODED_URL_ASSET['filename'], $mediaAsset->getFilename());
        $this->assertEquals(self::ENCODED_URL_ASSET['dirOne'], $mediaAsset->getDirOne());
        $this->assertEquals(self::ENCODED_URL_ASSET['dirTwo'], $mediaAsset->getDirTwo());
        $this->assertFileExists($assetPath);
        $this->assertEquals(self::ENCODED_URL_ASSET['filesize'], filesize($assetPath));
    }

    /**
     * In some cases, a .gifv URL renders an HTML page instead of a media source
     * such as a .gifv, .gif, or .mp4 file. As a result, attempting to detect
     * the correct MIME Type raises an error.
     *
     * The detection logic must be updated to include an HTML type, and in those
     * cases, attempt to reference and download the source's .mp4 link instead.
     *
     * Example (NSFW):
     *  - Reddit Post: https://www.reddit.com/r/Unexpected/comments/dvuois/people_are_so_immature/
     *  - Media Link: https://i.imgur.com/nL4zkco.gifv
     *
     * @return void
     */
    public function testVideoHtmlPage(): void
    {
        $context = new Context('DownloaderTest:testVideoHtmlPage');

        $htmlVideoAssetPath = sprintf(self::BASE_PATH_FORMAT, self::HTML_VIDEO_ASSET['dirOne'], self::HTML_VIDEO_ASSET['dirTwo']) . self::HTML_VIDEO_ASSET['filename'];
        $this->assertFileDoesNotExist($htmlVideoAssetPath);

        $content = $this->manager->syncContentByUrl($context, '/r/Unexpected/comments/dvuois/people_are_so_immature/');
        $post = $content->getPost();
        $this->assertEquals('https://i.imgur.com/nL4zkco.gifv', $post->getUrl());

        $mediaAsset = $post->getMediaAssets()->first();
        $this->assertFalse($mediaAsset->isDownloaded());
        $mediaAsset = $this->assetsManager->downloadAsset($mediaAsset);
        $this->assertTrue($mediaAsset->isDownloaded());

        $this->assertInstanceOf(Asset::class, $mediaAsset);
        $this->assertEquals(self::HTML_VIDEO_ASSET['sourceUrl'], $mediaAsset->getSourceUrl());
        $this->assertEquals(self::HTML_VIDEO_ASSET['filename'], $mediaAsset->getFilename());
        $this->assertEquals(self::HTML_VIDEO_ASSET['dirOne'], $mediaAsset->getDirOne());
        $this->assertEquals(self::HTML_VIDEO_ASSET['dirTwo'], $mediaAsset->getDirTwo());
        $this->assertFileExists($htmlVideoAssetPath);
        $this->assertEquals(self::HTML_VIDEO_ASSET['filesize'], filesize($htmlVideoAssetPath));

        // This is not a typical .mp4 download, so the audio should have already
        // been included with the .mp4 file. The separate Audio file should not
        // be set.
        $this->assertEmpty($mediaAsset->getAudioFilename());
        $this->assertEmpty($mediaAsset->getAudioSourceUrl());
    }

    /**
     * A somewhat edge-case has been discovered in which a Reddit Award asset
     * 404s on Reddit's side, causing the Award asset download logic to error
     * out.
     *
     * To address, add graceful degradation logic to avoid error-ing out on a 404
     * and to avoid persisting incomplete Award and Asset Entities.
     *
     * https://www.reddit.com/r/ketorecipes/comments/jcc799/keto_gummies_made_with_koolaid/
     *
     * @return void
     */
    public function testAwardAssetIcon404(): void
    {
        $context = new Context('DownloaderTest:testAwardAssetIcon404');

        $content = $this->manager->syncContentByUrl($context, '/r/ketorecipes/comments/jcc799/keto_gummies_made_with_koolaid/');
        $post = $content->getPost();

        $this->assertEquals('Keto gummies (made with kool-aid)', $post->getTitle());
        $awards = $post->getPostAwards();

        $notFoundAwardName = 'Excited';
        $missingAwardFound = false;
        foreach ($awards as $award) {
            if ($notFoundAwardName === $award->getAward()->getName()) {
                $missingAwardFound = true;
            }
        }

        $this->assertFalse($missingAwardFound);
    }

    /**
     * It has been found that if a Post had a Video Media Asset that was removed
     * by Reddit (for copyright reasons, for example), the Denormalization logic
     * will still attempt to reference the video, resulting in an error.
     *
     * To address, update the logic to explicitly check for the existence of the Video
     * property within the Post data using the following line:
     *  - !empty($postData['media']['reddit_video']['fallback_url']
     *
     * Once updated, verify with this test that the Post can now be synced
     * without throwing an error and without downloading any Media Assets
     * (because there are none).
     *
     * https://www.reddit.com/r/funny/comments/jtwoe0/the_cat_just_took_a_huge_mouthful/.json
     *
     * @return void
     */
    public function testAssetRemovedByReddit(): void
    {
        $context = new Context('DownloaderTest:testAssetRemovedByReddit');

        $content = $this->manager->syncContentByUrl($context, '/r/funny/comments/jtwoe0/the_cat_just_took_a_huge_mouthful/');
        $post = $content->getPost();

        $this->assertEquals('The cat just took a huge mouthful', $post->getTitle());
        $this->assertStringContainsString('Removed by reddit', $post->getLatestPostAuthorText()->getAuthorText()->getText());
        $this->assertEmpty($post->getMediaAssets());
    }

    /**
     * Verify the Icon Asset for an Award is persisted and downloaded locally.
     *
     * @return void
     */
    public function testSaveAwardIconAsset(): void
    {
        $context = new Context('DownloaderTest:testSaveAwardIconAsset');

        foreach ([self::AWARD_SILVER_ICON_ASSET, self::AWARD_FACEPALM_ICON_ASSET] as $asset) {
            $assetPath = sprintf(self::BASE_PATH_FORMAT, self::SUBREDDIT_BANNER_IMAGE_ASSET['dirOne'], self::SUBREDDIT_BANNER_IMAGE_ASSET['dirTwo']) . self::SUBREDDIT_BANNER_IMAGE_ASSET['filename'];
            $this->assertFileDoesNotExist($assetPath);
        }

        $commentUrl = 'https://www.reddit.com/r/Jokes/comments/y1vmdf/comment/is022vs';
        $content = $this->manager->syncContentFromJsonUrl($context, Kind::KIND_COMMENT, $commentUrl);
        $comment = $content->getComment();

        $commentAwards = $comment->getCommentAwards();
        $this->assertCount(2, $commentAwards);

        foreach ($commentAwards as $commentAward) {
            $award = $commentAward->getAward();
            $iconAsset = $award->getIconAsset();
            $this->assertFalse($iconAsset->isDownloaded());
            $iconAsset = $this->assetsManager->downloadAsset($iconAsset);
            $this->assertTrue($iconAsset->isDownloaded());

            $expectedAsset = self::AWARD_FACEPALM_ICON_ASSET;
            if ($award->getRedditId() === 'gid_1') {
                $expectedAsset = self::AWARD_SILVER_ICON_ASSET;
            }

            $iconAssetPath = sprintf(self::BASE_PATH_FORMAT, $expectedAsset['dirOne'], $expectedAsset['dirTwo']) . $expectedAsset['filename'];
            $this->assertInstanceOf(Asset::class, $iconAsset);
            $this->assertEquals($expectedAsset['sourceUrl'], $iconAsset->getSourceUrl());
            $this->assertEquals($expectedAsset['filename'], $iconAsset->getFilename());
            $this->assertEquals($expectedAsset['dirOne'], $iconAsset->getDirOne());
            $this->assertEquals($expectedAsset['dirTwo'], $iconAsset->getDirTwo());
            $this->assertFileExists($iconAssetPath);
            $this->assertEquals($expectedAsset['filesize'], filesize($iconAssetPath));
        }
    }

    /**
     * Verify assets associated to a Subreddit, such as its Header Banner Image,
     * are also persisted and downloaded locally.
     *
     * https://www.reddit.com/r/dbz/comments/10bt9qv/rewatching_the_namek_saga/
     *
     * @return void
     */
    public function testSaveSubredditAssets(): void
    {
        $context = new Context('DownloaderTest:testSaveSubredditAssets');

        $iconImageAssetPath = sprintf(self::BASE_PATH_FORMAT, self::SUBREDDIT_ICON_IMAGE_ASSET['dirOne'], self::SUBREDDIT_ICON_IMAGE_ASSET['dirTwo']) . self::SUBREDDIT_ICON_IMAGE_ASSET['filename'];
        $bannerBackgroundImageAssetPath = sprintf(self::BASE_PATH_FORMAT, self::SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET['dirOne'], self::SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET['dirTwo']) . self::SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET['filename'];
        $bannerImageAssetPath = sprintf(self::BASE_PATH_FORMAT, self::SUBREDDIT_BANNER_IMAGE_ASSET['dirOne'], self::SUBREDDIT_BANNER_IMAGE_ASSET['dirTwo']) . self::SUBREDDIT_BANNER_IMAGE_ASSET['filename'];
        foreach ([$iconImageAssetPath, $bannerBackgroundImageAssetPath, $bannerImageAssetPath] as $assetPath) {
            $this->assertFileDoesNotExist($assetPath);
        }

        $content = $this->manager->syncContentFromApiByFullRedditId($context, 't3_10bt9qv');
        $subreddit = $content->getPost()->getSubreddit();

        $this->assertEquals('dbz', $subreddit->getName());
        $this->assertEquals('t5_2sdu8', $subreddit->getRedditId());
        $this->assertEquals('Dragon World', $subreddit->getTitle());
        $this->assertEquals("A subreddit for the entire Dragon Ball franchise.  \ndiscord.gg/dbz", $subreddit->getPublicDescription());

        // Icon Image Asset.
        $iconImageAsset = $subreddit->getIconImageAsset();
        $this->assertFalse($iconImageAsset->isDownloaded());
        $iconImageAsset = $this->assetsManager->downloadAsset($iconImageAsset);
        $this->assertTrue($iconImageAsset->isDownloaded());

        $this->assertInstanceOf(Asset::class, $iconImageAsset);
        $this->assertEquals(self::SUBREDDIT_ICON_IMAGE_ASSET['sourceUrl'], $iconImageAsset->getSourceUrl());
        $this->assertEquals(self::SUBREDDIT_ICON_IMAGE_ASSET['filename'], $iconImageAsset->getFilename());
        $this->assertEquals(self::SUBREDDIT_ICON_IMAGE_ASSET['dirOne'], $iconImageAsset->getDirOne());
        $this->assertEquals(self::SUBREDDIT_ICON_IMAGE_ASSET['dirTwo'], $iconImageAsset->getDirTwo());
        $this->assertFileExists($iconImageAssetPath);
        $this->assertEquals(self::SUBREDDIT_ICON_IMAGE_ASSET['filesize'], filesize($iconImageAssetPath));

        // Banner Background Image Asset.
        $bannerBackgroundImageAsset = $subreddit->getBannerBackgroundImageAsset();
        $this->assertFalse($bannerBackgroundImageAsset->isDownloaded());
        $bannerBackgroundImageAsset = $this->assetsManager->downloadAsset($bannerBackgroundImageAsset);
        $this->assertTrue($bannerBackgroundImageAsset->isDownloaded());

        $this->assertInstanceOf(Asset::class, $bannerBackgroundImageAsset);
        $this->assertEquals(self::SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET['sourceUrl'], $bannerBackgroundImageAsset->getSourceUrl());
        $this->assertEquals(self::SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET['filename'], $bannerBackgroundImageAsset->getFilename());
        $this->assertEquals(self::SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET['dirOne'], $bannerBackgroundImageAsset->getDirOne());
        $this->assertEquals(self::SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET['dirTwo'], $bannerBackgroundImageAsset->getDirTwo());
        $this->assertFileExists($bannerBackgroundImageAssetPath);
        $this->assertEquals(self::SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET['filesize'], filesize($bannerBackgroundImageAssetPath));

        // Banner Image Asset.
        $bannerImageAsset = $subreddit->getBannerImageAsset();
        $this->assertFalse($bannerImageAsset->isDownloaded());
        $bannerImageAsset = $this->assetsManager->downloadAsset($bannerImageAsset);
        $this->assertTrue($bannerImageAsset->isDownloaded());

        $this->assertInstanceOf(Asset::class, $bannerImageAsset);
        $this->assertEquals(self::SUBREDDIT_BANNER_IMAGE_ASSET['sourceUrl'], $bannerImageAsset->getSourceUrl());
        $this->assertEquals(self::SUBREDDIT_BANNER_IMAGE_ASSET['filename'], $bannerImageAsset->getFilename());
        $this->assertEquals(self::SUBREDDIT_BANNER_IMAGE_ASSET['dirOne'], $bannerImageAsset->getDirOne());
        $this->assertEquals(self::SUBREDDIT_BANNER_IMAGE_ASSET['dirTwo'], $bannerImageAsset->getDirTwo());
        $this->assertFileExists($bannerImageAssetPath);
        $this->assertEquals(self::SUBREDDIT_BANNER_IMAGE_ASSET['filesize'], filesize($bannerImageAssetPath));
    }

    /**
     * Verify saving assets from Contents retrieved via the Reddit API.
     *
     * @dataProvider saveAssetsFromPostsDataProvider
     *
     * @param  string  $contentUrl
     * @param  string  $redditId
     * @param  array  $assets
     * @param  array  $thumbAsset
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function testSaveAssetsFromContentsSyncedFromApi(
        string $contentUrl,
        string $redditId,
        array $assets,
        array $thumbAsset,
    ) {
        $context = new Context('DownloaderTest:testSaveAssetsFromContentsSyncedFromApi');

        $this->verifyPreDownloadAssertions($assets, $thumbAsset);

        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_LINK . '_' . $redditId, downloadAssets: true);
        $this->verifyContentsDownloads($content, $assets, $thumbAsset);
    }

    /**
     * Verify saving assets from Contents retrieved via their Reddit URLs.
     *
     * @dataProvider saveAssetsFromPostsDataProvider
     *
     * @param  string  $contentUrl
     * @param  string  $redditId
     * @param  array  $assets
     * @param  array  $thumbAsset
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function testSaveAssetsFromContentUrls(
        string $contentUrl,
        string $redditId,
        array $assets,
        array $thumbAsset,
    ) {
        $context = new Context('DownloaderTest:testSaveAssetsFromContentUrls');

        $this->verifyPreDownloadAssertions($assets, $thumbAsset);

        $content = $this->manager->syncContentByUrl($context, $contentUrl, downloadAssets: true);
        $this->verifyContentsDownloads($content, $assets, $thumbAsset);
    }

    /**
     * @return array
     */
    public function saveAssetsFromPostsDataProvider(): array
    {
        return [
            'Image' => [
                'contentUrl' => 'https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/',
                'redditId' => 'vepbt0',
                'assets' => [
                    [
                        'sourceUrl' => 'https://i.imgur.com/ThRMZx5.jpg',
                        'filename' => '46f25e262b7481f62265d0d879a2729d.jpg',
                        'dirOne' => '4',
                        'dirTwo' => '6f',
                        'filesize' => 809422,
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/eVhpmEiR3ItbKk6R0SDI6C1XM5ONek_xcQIIhtCA5YQ.jpg',
                    'filename' => '40422880b7fc1bcef547015a333724d9_thumb.jpg',
                    'dirOne' => '4',
                    'dirTwo' => '04',
                    'filesize' => 7219,
                ],
            ],
            'Image | Reddit-hosted' => [
                'contentUrl' => 'https://www.reddit.com/r/coolguides/comments/won0ky/i_learned_how_to_whistle_from_this_in_less_than_5/',
                'redditId' => 'won0ky',
                'assets' => [
                    [
                        'sourceUrl' => 'https://i.redd.it/cnfk33iv9sh91.jpg',
                        'filename' => '2af6ecd6022400dc4c4fecc4714b8ab2.jpg',
                        'dirOne' => '2',
                        'dirTwo' => 'af',
                        'filesize' => 362284,
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/_9QxeKKVgR-o6E9JE-vydP1i5OpkyEziomCERjBlSOU.jpg',
                    'filename' => 'd0fed5cf6a017c8e7cb17bc9197c46ee_thumb.jpg',
                    'dirOne' => 'd',
                    'dirTwo' => '0f',
                    'filesize' => 8266,
                ],
            ],
            'Image | Text Post' => [
                'contentUrl' => 'https://www.reddit.com/r/Tremors/comments/utsmkw/tremors_poster_for_gallery1988',
                'redditId' => 'utsmkw',
                'assets' => [
                    [
                        'sourceUrl' => 'https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&v=enabled&s=8a5a16f886e24f206b0dbea9cc0e5a6cd25ef018',
                        'filename' => 'ff9831e41692d083012ffb5157a66399.jpg',
                        'dirOne' => 'f',
                        'dirTwo' => 'f9',
                        'filesize' => 176023,
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/q06gPIAKixPJS38j1dkiwiEqiA6k4kqie84T5yLgt4o.jpg',
                    'filename' => '91659ca7511ebb61c6ae12ee14728ef5_thumb.jpg',
                    'dirOne' => '9',
                    'dirTwo' => '16',
                    'filesize' => 6120,
                ],
            ],
            'GIF' => [
                'contentUrl' => 'https://www.reddit.com/r/SquaredCircle/comments/8ung3q/when_people_tell_me_that_wrestling_is_fake_i/',
                'redditId' => '8ung3q',
                'assets' => [
                    [
                        'sourceUrl' => 'http://i.imgur.com/RWFWUYi.gif',
                        'filename' => 'f6c1d1af71bc3040f4b165c0f0e14a0e.mp4',
                        'dirOne' => 'f',
                        'dirTwo' => '6c',
                        'filesize' => 1502792,
                    ],
                ],
                'thumbAsset' => [],
            ],
            'Video' => [
                // @TODO: Add initial assertion to ensure ffmpeg is installed.
                'contentUrl' => 'https://www.reddit.com/r/Unexpected/comments/tl8qic/i_think_i_married_a_psychopath/',
                'redditId' => 'tl8qic',
                'assets' => [
                    [
                        'sourceUrl' => 'https://v.redd.it/8u3caw3zm6p81/DASH_720.mp4?source=fallback',
                        'filename' => '48467e749b3af6948b71047136067d79.mp4',
                        'dirOne' => '4',
                        'dirTwo' => '84',
                        'filesize' => 3405946,
                        'audioSourceUrl' => 'https://v.redd.it/8u3caw3zm6p81/DASH_audio.mp4',
                        'audioFilename' => '8u3caw3zm6p81_audio.mp4',
                    ],
                ],
                'thumbAsset' => [],
            ],
            'Video | No Audio' => [
                /**
                 * Validate persisting a Reddit-hosted Video that does not contain audio.
                 *
                 * Only the Video file (.mp4) should be downloaded and no Audio properties
                 * should be set.
                 *
                 * https://www.reddit.com/r/ProgrammerHumor/comments/wfylnl/when_you_use_a_new_library_without_reading_the/
                 */
                'contentUrl' => 'https://www.reddit.com/r/ProgrammerHumor/comments/wfylnl/when_you_use_a_new_library_without_reading_the/',
                'redditId' => 'wfylnl',
                'assets' => [
                    [
                        'sourceUrl' => 'https://v.redd.it/bofh9q9jkof91/DASH_720.mp4?source=fallback',
                        'filename' => '9efda9eb6caeae9aa75f2851281a5d91.mp4',
                        'dirOne' => '9',
                        'dirTwo' => 'ef',
                        'filesize' => 1563120,
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/EP5Wgd7mgrsKVgPOFgiAvDblLmm5qNSBnSAvqzAZFcE.jpg',
                    'filename' => '6161d7d535bc7c3e5eb7c59b8c8abf4a_thumb.jpg',
                    'dirOne' => '6',
                    'dirTwo' => '16',
                    'filesize' => 6407,
                ],
            ],
            'Image Gallery' => [
                'contentUrl' => 'https://www.reddit.com/r/Tremors/comments/v27nr7/all_my_recreations_of_magazine_covers_from/',
                'redditId' => 'v27nr7',
                'assets' => [
                    [
                        'sourceUrl' => 'https://preview.redd.it/zy4xzki4jx291.jpg?width=2543&format=pjpg&auto=webp&v=enabled&s=b0cf2604fd569427de1dafb369f1339d59c4f319',
                        'filename' => '830c1e333479ed797fe848a39d5a0d2c.jpg',
                        'dirOne' => '8',
                        'dirTwo' => '30',
                        'filesize' => 548236,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/exunuhm4jx291.jpg?width=612&format=pjpg&auto=webp&v=enabled&s=939b5eb1e639801c2e64daf805da1f00f658b93a',
                        'filename' => 'e68b955d98177394370056dcd3625208.jpg',
                        'dirOne' => 'e',
                        'dirTwo' => '68',
                        'filesize' => 73414,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/rs5yhje4jx291.jpg?width=1080&format=pjpg&auto=webp&v=enabled&s=8b73da617e1183de4589b87231d09676c8eeb1db',
                        'filename' => '82722e2ec6cf58a33f9ad1ced844d54d.jpg',
                        'dirOne' => '8',
                        'dirTwo' => '27',
                        'filesize' => 84855,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/s0yrptf4jx291.jpg?width=612&format=pjpg&auto=webp&v=enabled&s=a01a35da6a1d30198e535bff5ee1011a9a905760',
                        'filename' => 'd10730e41dd145a41654850aff314b44.jpg',
                        'dirOne' => 'd',
                        'dirTwo' => '10',
                        'filesize' => 52506,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/jpmunxg4jx291.jpg?width=1080&format=pjpg&auto=webp&v=enabled&s=f1f2950bdbaaf9c0120349536da6dfedfd5c4a2f',
                        'filename' => 'e1d6b0043ef8c4f07c59b9432ce80b25.jpg',
                        'dirOne' => 'e',
                        'dirTwo' => '1d',
                        'filesize' => 129301,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/6p3g7c64jx291.jpg?width=2543&format=pjpg&auto=webp&v=enabled&s=8c6a551a7cb35c5e42173bc22c6f255c4c07c13c',
                        'filename' => '66147ecef1eda09b6534dbc517a2eddb.jpg',
                        'dirOne' => '6',
                        'dirTwo' => '61',
                        'filesize' => 467076,
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://a.thumbs.redditmedia.com/oicSvcPsUxSfSzil8Hh7b1QD1T_GJq_vIo7iFtrkDd0.jpg',
                    'filename' => 'f789f033a86702edd3423d4da0600471_thumb.jpg',
                    'dirOne' => 'f',
                    'dirTwo' => '78',
                    'filesize' => 8198,
                ],
            ],
            'GIF Gallery' => [
                'contentUrl' => 'https://www.reddit.com/r/Terminator/comments/wvg39c/terminator_2_teaser_trailer/',
                'redditId' => 'wvg39c',
                'assets' => [
                    [
                        'sourceUrl' => 'https://preview.redd.it/hzhtz9fydej91.gif?format=mp4&v=enabled&s=47e39b0a722c44d3574d128da260a3c215812f35',
                        'filename' => '6bc287524c859df4f3e3b0971f647ed2.mp4',
                        'dirOne' => '6',
                        'dirTwo' => 'bc',
                        'filesize' => 179072,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/pwhjkwyxdej91.gif?format=mp4&v=enabled&s=f4d165ed609dd65758ce49f958074d4206a784b5',
                        'filename' => 'fa9a19ea20b42fcf69814d89a953427b.mp4',
                        'dirOne' => 'f',
                        'dirTwo' => 'a9',
                        'filesize' => 419880,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/59hsb44ydej91.gif?format=mp4&v=enabled&s=c470715029f618028026c0193a99d08ff308c1ec',
                        'filename' => 'c61a987805dfcf94cc720a9263d048f7.mp4',
                        'dirOne' => 'c',
                        'dirTwo' => '61',
                        'filesize' => 799835,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/h7tin1jydej91.gif?format=mp4&v=enabled&s=bfd0493433e222daa3dd0b3b2636462cd26e9bae',
                        'filename' => '86935f653967c188b10c07fc20b1ebb1.mp4',
                        'dirOne' => '8',
                        'dirTwo' => '69',
                        'filesize' => 843758,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/6b6pwxvydej91.gif?format=mp4&v=enabled&s=193a375ae0bedbbabaabf4c06704c8f8eba99881',
                        'filename' => 'f8538ce4daeebed38254d2a71be2960a.mp4',
                        'dirOne' => 'f',
                        'dirTwo' => '85',
                        'filesize' => 509102,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/9fy58fazdej91.gif?format=mp4&v=enabled&s=99cdd935ad2a2261aaaefdaf569decfa0456d775',
                        'filename' => '4b477864b3e23c070ad204e004b9ecac.mp4',
                        'dirOne' => '4',
                        'dirTwo' => 'b4',
                        'filesize' => 735407,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/42cnannxdej91.gif?format=mp4&v=enabled&s=eba55f272ad80243afb6397d20cdadcf876214c9',
                        'filename' => '270a2aff1a822e30e95147ac5bc1b1ad.mp4',
                        'dirOne' => '2',
                        'dirTwo' => '70',
                        'filesize' => 730840,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/yvs1hq2zdej91.gif?format=mp4&v=enabled&s=8b9984580ee1a575c6aca1d2151a91ac61847afb',
                        'filename' => '53568813525709a9307b50302313c2e2.mp4',
                        'dirOne' => '5',
                        'dirTwo' => '35',
                        'filesize' => 576520,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/lkve7ervdej91.gif?format=mp4&v=enabled&s=e30ca459633d5724ac22f39eee3b95b8b00e0bce',
                        'filename' => '9d5b919f4887901d6ed4b51cb152368d.mp4',
                        'dirOne' => '9',
                        'dirTwo' => 'd5',
                        'filesize' => 722310,
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/J-7WpMcyHF7C8e75BZiVEgJyK6jSJLpuTJ4srJI1ojM.jpg',
                    'filename' => 'a6824477002104ebfe9dbe4e6feed00e_thumb.jpg',
                    'dirOne' => 'a',
                    'dirTwo' => '68',
                    'filesize' => 2795,
                ],
            ],
        ];
    }

    public function tearDown(): void
    {
        $this->cleanupAssets();

        parent::tearDown();
    }

    /**
     * Execute the assertions required before attempting to download assets for
     * testing.
     *
     * @param  array  $assets
     * @param  array  $thumbAsset
     *
     * @return void
     */
    private function verifyPreDownloadAssertions(array $assets, array $thumbAsset): void
    {
        if (!empty($thumbAsset)) {
            $expectedThumbPath = $this->getExpectedThumbPath($thumbAsset);
            $this->assertFileDoesNotExist($expectedThumbPath);
        }

        foreach ($assets as $asset) {
            $assetPath = sprintf(self::BASE_PATH_FORMAT, $asset['dirOne'], $asset['dirTwo']) . $asset['filename'];
            $this->assertFileDoesNotExist($assetPath);
        }
    }

    /**
     * After downloading the targeted assets, run through the assertions for the
     * files downloaded and expected persisted data.
     *
     * @param  Content  $content
     * @param  array  $assets
     * @param  array  $thumbAsset
     *
     * @return void
     */
    private function verifyContentsDownloads(
        Content $content,
        array $assets,
        array $thumbAsset,
    ): void {
        $post = $content->getPost();

        // Assert assets were saved locally.
        foreach ($assets as $asset) {
            $assetPath = sprintf(self::BASE_PATH_FORMAT, $asset['dirOne'], $asset['dirTwo']) . $asset['filename'];
            $this->assertFileExists($assetPath);
            $this->assertEquals($asset['filesize'], filesize($assetPath));
        }

        if (!empty($thumbAsset)) {
            $expectedThumbPath = $this->getExpectedThumbPath($thumbAsset);
            $this->assertFileExists($expectedThumbPath);
        }

        // Assert assets were persisted to the database and associated to the
        // intended Post.
        $mediaAssets = $post->getMediaAssets();
        $this->assertCount(count($assets), $mediaAssets);

        // Assert assets can be retrieved from the database and are
        // associated to the intended Post.
        foreach ($assets as $asset) {
            /** @var Asset $mediaAsset */
            $mediaAsset = $this->entityManager
                ->getRepository(Asset::class)
                ->findOneBy(['filename' => $asset['filename']])
            ;

            $this->assertEquals($asset['sourceUrl'], $mediaAsset->getSourceUrl());
            $this->assertEquals($asset['dirOne'], $mediaAsset->getDirOne());
            $this->assertEquals($asset['dirTwo'], $mediaAsset->getDirTwo());
            $this->assertEquals($post->getId(), $mediaAsset->getPost()->getId());

            if (!empty($asset['audioSourceUrl'])) {
                $this->assertEquals($asset['audioSourceUrl'], $mediaAsset->getAudioSourceUrl());
            } else {
                $this->assertEmpty($mediaAsset->getAudioSourceUrl());
            }

            if (!empty($asset['audioFilename'])) {
                $this->assertEquals($asset['audioFilename'], $mediaAsset->getAudioFilename());
            } else {
                $this->assertEmpty($mediaAsset->getAudioFilename());
            }
        }

        if (!empty($thumbAsset)) {
            $thumbnailAsset = $post->getThumbnailAsset();
            $this->assertEquals($thumbAsset['sourceUrl'], $thumbnailAsset->getSourceUrl());
            $this->assertEquals($thumbAsset['filename'], $thumbnailAsset->getFilename());
            $this->assertEquals($thumbAsset['filesize'], filesize($expectedThumbPath));
        }
    }

    /**
     * Generate and return the expected path based on the provided Thumb asset
     * parameters.
     *
     * @param  array  $thumbAsset
     *
     * @return string
     */
    private function getExpectedThumbPath(array $thumbAsset): string
    {
        $thumbBasePath = sprintf(self::BASE_PATH_FORMAT, $thumbAsset['dirOne'], $thumbAsset['dirTwo']);

        return $thumbBasePath . $thumbAsset['filename'];
    }

    /**
     * Ensure any expected asset files are purged from the system before and
     * after testing.
     *
     * @return void
     */
    private function cleanupAssets(): void
    {
        $filesystem = new Filesystem();

        $targetDataArray = $this->saveAssetsFromPostsDataProvider();
        foreach ($targetDataArray as $targetData) {

            foreach ($targetData['assets'] as $asset) {
                $assetPath = sprintf(self::BASE_PATH_FORMAT, $asset['dirOne'], $asset['dirTwo']) . $asset['filename'];
                $filesystem->remove($assetPath);
            }

            if (!empty($targetData['thumbAsset'])) {
                $thumbAssetPath= sprintf(self::BASE_PATH_FORMAT, $targetData['thumbAsset']['dirOne'], $targetData['thumbAsset']['dirTwo']) . $targetData['thumbAsset']['filename'];
                $filesystem->remove($thumbAssetPath);
            }
        }

        $additionalAssets = [
            self::SUBREDDIT_ICON_IMAGE_ASSET,
            self::SUBREDDIT_BANNER_BACKGROUND_IMAGE_ASSET,
            self::SUBREDDIT_BANNER_IMAGE_ASSET,
            self::AWARD_SILVER_ICON_ASSET,
            self::AWARD_FACEPALM_ICON_ASSET,
            self::HTML_VIDEO_ASSET,
            self::ENCODED_URL_ASSET,
        ];

        foreach ($additionalAssets as $asset) {
            $assetPath = sprintf(self::BASE_PATH_FORMAT, $asset['dirOne'], $asset['dirTwo']) . $asset['filename'];
            $filesystem->remove($assetPath);
        }
    }
}
