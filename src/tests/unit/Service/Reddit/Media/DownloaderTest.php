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
        'filesize' => 864092,
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
        'sourceUrl' => 'https://i.imgur.com/nL4zkco.gifv',
        'filename' => '9c9258c5b29a70baddfa21faa33e61ce.png',
        'dirOne' => '9',
        'dirTwo' => 'c9',
        'filesize' => 503,
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
                        'sourceUrl' => 'https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&s=7cab4910712115bb273171653cc754b9077c1455',
                        'filename' => 'c155ff20b6decce40fbc3aeafbf94575.jpg',
                        'dirOne' => 'c',
                        'dirTwo' => '15',
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
                        'sourceUrl' => 'https://preview.redd.it/zy4xzki4jx291.jpg?width=2543&format=pjpg&auto=webp&s=2f4c3f05a428019b6754ca3c9ab8d3122df14664',
                        'filename' => 'c4678fd0837c4e11ecb6314652149c47.jpg',
                        'dirOne' => 'c',
                        'dirTwo' => '46',
                        'filesize' => 548236,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/exunuhm4jx291.jpg?width=612&format=pjpg&auto=webp&s=1aadfb05549500b4a3e61f377a87b6739d7e92e7',
                        'filename' => '993a6e00afa4a4fa0761387846cd4e07.jpg',
                        'dirOne' => '9',
                        'dirTwo' => '93',
                        'filesize' => 73414,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/rs5yhje4jx291.jpg?width=1080&format=pjpg&auto=webp&s=d6d30ce00bf261edf76802fd79a455ad08bc0d62',
                        'filename' => '5b5193a4e08213036c17ca66bb595f75.jpg',
                        'dirOne' => '5',
                        'dirTwo' => 'b5',
                        'filesize' => 84855,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/s0yrptf4jx291.jpg?width=612&format=pjpg&auto=webp&s=b7442ac83a19780a34ababb9439ef857a672a13f',
                        'filename' => 'f1a66f7571dd3641f208d87cf05708a4.jpg',
                        'dirOne' => 'f',
                        'dirTwo' => '1a',
                        'filesize' => 52506,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/jpmunxg4jx291.jpg?width=1080&format=pjpg&auto=webp&s=0ea1e60464a6905e72f06a70c4e781ec16ac0af6',
                        'filename' => '901fba34217c683b8652cd262987f2c2.jpg',
                        'dirOne' => '9',
                        'dirTwo' => '01',
                        'filesize' => 129301,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/6p3g7c64jx291.jpg?width=2543&format=pjpg&auto=webp&s=5914dc1cd03aa246d5a22810bf64098674092691',
                        'filename' => '8e1b320bc5b685c4892b9c6a1fc27b44.jpg',
                        'dirOne' => '8',
                        'dirTwo' => 'e1',
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
                        'sourceUrl' => 'https://preview.redd.it/hzhtz9fydej91.gif?format=mp4&s=43a197453fe9eebf82404c643507ed622f9760e4',
                        'filename' => '78d696fc1850008175251a6a29fb7b00.mp4',
                        'dirOne' => '7',
                        'dirTwo' => '8d',
                        'filesize' => 179072,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/pwhjkwyxdej91.gif?format=mp4&s=25ac9c9a6dc03ad3d7ef36f859c13f5edcde08fb',
                        'filename' => 'e80d9aac437f4eb98798157a3cb32ecd.mp4',
                        'dirOne' => 'e',
                        'dirTwo' => '80',
                        'filesize' => 419880,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/59hsb44ydej91.gif?format=mp4&s=77fff215f5af86ce035b0d05de9ca66649458ebc',
                        'filename' => '41176ed29d7151d1fa43cf0f89d541c0.mp4',
                        'dirOne' => '4',
                        'dirTwo' => '11',
                        'filesize' => 799835,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/h7tin1jydej91.gif?format=mp4&s=4eb0e10b22e5e6962c2f58bf57e7f78ab8dab98d',
                        'filename' => '905d964aa79bc012cffddadd55d8ecc4.mp4',
                        'dirOne' => '9',
                        'dirTwo' => '05',
                        'filesize' => 843758,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/lkve7ervdej91.gif?format=mp4&s=5a76bc4c82dcb15cb9d23dc6f62eb4c65e424598',
                        'filename' => '4d198b8f5f67bd90d0ecb3e3640aa3e1.mp4',
                        'dirOne' => '4',
                        'dirTwo' => 'd1',
                        'filesize' => 722310,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/9fy58fazdej91.gif?format=mp4&s=d7f53d9e580e2520acd7a02bd22db1d645249141',
                        'filename' => 'fb3fc8c7381824dc67e454b4685e9cda.mp4',
                        'dirOne' => 'f',
                        'dirTwo' => 'b3',
                        'filesize' => 735407,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/42cnannxdej91.gif?format=mp4&s=7376b9c6327d07dbfbc2b23e903f0a0b8e28e559',
                        'filename' => '514be94afb86a8b62431376751a02725.mp4',
                        'dirOne' => '5',
                        'dirTwo' => '14',
                        'filesize' => 730840,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/yvs1hq2zdej91.gif?format=mp4&s=91d6ca9b40ba839f9d16b5f187332646df4047a4',
                        'filename' => '9540dbafd6e4a1c7bf13d1dd84396347.mp4',
                        'dirOne' => '9',
                        'dirTwo' => '54',
                        'filesize' => 576520,
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/6b6pwxvydej91.gif?format=mp4&s=22b28c51afe45f9586f83a2d722522154704b62b',
                        'filename' => '67eded7207757460257dfe644f99ce1d.mp4',
                        'dirOne' => '6',
                        'dirTwo' => '7e',
                        'filesize' => 509102,
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
