<?php
declare(strict_types=1);

namespace App\Tests\unit\Service\Reddit\Media;

use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\MediaAsset;
use App\Service\Reddit\Manager;
use Doctrine\ORM\EntityManager;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class DownloaderTest extends KernelTestCase
{
    const BASE_PATH_FORMAT = '/var/www/mra/public/r-media/%s/%s/';

    private Manager $manager;

    private EntityManager $entityManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->entityManager = $container->get('doctrine')->getManager();

        $this->cleanupAssets();
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
        $this->verifyPreDownloadAssertions($assets, $thumbAsset);

        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_LINK . '_' . $redditId);
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
        $this->verifyPreDownloadAssertions($assets, $thumbAsset);

        $content = $this->manager->syncContentByUrl($contentUrl);
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
                        'filename' => 'faac0cc02f38ca7aa896f5dafdeaacb9.jpg',
                        'dirOne' => 'f',
                        'dirTwo' => 'aa',
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/eVhpmEiR3ItbKk6R0SDI6C1XM5ONek_xcQIIhtCA5YQ.jpg',
                    'filename' => 'cd79c58c96cbc4684f4aef775c47f5a5_thumb.jpg',
                    'dirOne' => 'c',
                    'dirTwo' => 'd7',
                ],
            ],
            'Image | Reddit-hosted' => [
                'contentUrl' => 'https://www.reddit.com/r/coolguides/comments/won0ky/i_learned_how_to_whistle_from_this_in_less_than_5/',
                'redditId' => 'won0ky',
                'assets' => [
                    [
                        'sourceUrl' => 'https://i.redd.it/cnfk33iv9sh91.jpg',
                        'filename' => '44cdd5b77a44b3ebd1e955946e71efc0.jpg',
                        'dirOne' => '4',
                        'dirTwo' => '4c',
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/_9QxeKKVgR-o6E9JE-vydP1i5OpkyEziomCERjBlSOU.jpg',
                    'filename' => '5f1ff800d8d3dbdec298e3969b5fcbd2_thumb.jpg',
                    'dirOne' => '5',
                    'dirTwo' => 'f1',
                ],
            ],
            'Image | Text Post' => [
                'contentUrl' => 'https://www.reddit.com/r/Tremors/comments/utsmkw/tremors_poster_for_gallery1988',
                'redditId' => 'utsmkw',
                'assets' => [
                    [
                        'sourceUrl' => 'https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&v=enabled&s=8a5a16f886e24f206b0dbea9cc0e5a6cd25ef018',
                        'filename' => '4c1777c271523e2b78cf5f4f6ebda336.jpg',
                        'dirOne' => '4',
                        'dirTwo' => 'c1',
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/q06gPIAKixPJS38j1dkiwiEqiA6k4kqie84T5yLgt4o.jpg',
                    'filename' => '5a5859e3f92e5fb89c5971666c37a682_thumb.jpg',
                    'dirOne' => '5',
                    'dirTwo' => 'a5',
                ],
            ],
            'GIF' => [
                'contentUrl' => 'https://www.reddit.com/r/me_irl/comments/wgb8wj/me_irl/',
                'redditId' => 'wgb8wj',
                'assets' => [
                    [
                        'sourceUrl' => 'https://preview.redd.it/kanpjvgbarf91.gif?format=mp4&v=enabled&s=a156b30a7caf0da0c73550b61b0e11e938d92c3b',
                        'filename' => '1a20e9b5017645a72dedbf8e74b6851b.mp4',
                        'dirOne' => '1',
                        'dirTwo' => 'a2',
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://a.thumbs.redditmedia.com/DI9yoWanjzCXyy5kF8-JFfP-SPg2__nhBo0HNSxU8W4.jpg',
                    'filename' => 'fe2b035aaeed849079008231923cf160_thumb.jpg',
                    'dirOne' => 'f',
                    'dirTwo' => 'e2',
                ],
            ],
            'Video' => [
                // @TODO: Add initial assertion to ensure ffmpeg is installed.
                'contentUrl' => 'https://www.reddit.com/r/Unexpected/comments/tl8qic/i_think_i_married_a_psychopath/',
                'redditId' => 'tl8qic',
                'assets' => [
                    [
                        'sourceUrl' => 'https://v.redd.it/8u3caw3zm6p81/DASH_720.mp4?source=fallback',
                        'filename' => 'a01b41d34f5bb8bceb7540fa1b84728a.mp4',
                        'dirOne' => 'a',
                        'dirTwo' => '01',
                        'audioSourceUrl' => 'https://v.redd.it/8u3caw3zm6p81/DASH_audio.mp4',
                        'audioFilename' => '8u3caw3zm6p81_audio.mp4',
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/CPQpNEdyLw1Q2bK0jIpY8dLUtLzmegTqKJQMp5ONxto.jpg',
                    'filename' => 'e70ab5ad74e5a52e6d1f14d92b7f2187_thumb.jpg',
                    'dirOne' => 'e',
                    'dirTwo' => '70',
                ],
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
                        'filename' => '17de4f10fe97940aba8170d1eec6caf0.mp4',
                        'dirOne' => '1',
                        'dirTwo' => '7d',
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/EP5Wgd7mgrsKVgPOFgiAvDblLmm5qNSBnSAvqzAZFcE.jpg',
                    'filename' => '7151e2d464c3e108fc921134cd003d25_thumb.jpg',
                    'dirOne' => '7',
                    'dirTwo' => '15',
                ],
            ],
            'Image Gallery' => [
                'contentUrl' => 'https://www.reddit.com/r/Tremors/comments/v27nr7/all_my_recreations_of_magazine_covers_from/',
                'redditId' => 'v27nr7',
                'assets' => [
                    [
                        'sourceUrl' => 'https://preview.redd.it/zy4xzki4jx291.jpg?width=2543&format=pjpg&auto=webp&v=enabled&s=b0cf2604fd569427de1dafb369f1339d59c4f319',
                        'filename' => 'e9d5f407c7771dee3694290a14378f19.jpg',
                        'dirOne' => 'e',
                        'dirTwo' => '9d',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/exunuhm4jx291.jpg?width=612&format=pjpg&auto=webp&v=enabled&s=939b5eb1e639801c2e64daf805da1f00f658b93a',
                        'filename' => 'a1f009e2b2e71a7432437aa907bcabd8.jpg',
                        'dirOne' => 'a',
                        'dirTwo' => '1f',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/rs5yhje4jx291.jpg?width=1080&format=pjpg&auto=webp&v=enabled&s=8b73da617e1183de4589b87231d09676c8eeb1db',
                        'filename' => '0c3f6c913219920f7b4f7b69c557aa1e.jpg',
                        'dirOne' => '0',
                        'dirTwo' => 'c3',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/s0yrptf4jx291.jpg?width=612&format=pjpg&auto=webp&v=enabled&s=a01a35da6a1d30198e535bff5ee1011a9a905760',
                        'filename' => '73bc5e0da29fab2d4a1190119eaaeb75.jpg',
                        'dirOne' => '7',
                        'dirTwo' => '3b',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/jpmunxg4jx291.jpg?width=1080&format=pjpg&auto=webp&v=enabled&s=f1f2950bdbaaf9c0120349536da6dfedfd5c4a2f',
                        'filename' => '4df1192cc7dd1f46c6b53bf6f4bfc45b.jpg',
                        'dirOne' => '4',
                        'dirTwo' => 'df',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/6p3g7c64jx291.jpg?width=2543&format=pjpg&auto=webp&v=enabled&s=8c6a551a7cb35c5e42173bc22c6f255c4c07c13c',
                        'filename' => '792e4e54b0582acce505c21217e638dc.jpg',
                        'dirOne' => '7',
                        'dirTwo' => '92',
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://a.thumbs.redditmedia.com/oicSvcPsUxSfSzil8Hh7b1QD1T_GJq_vIo7iFtrkDd0.jpg',
                    'filename' => 'b1b23689fb7fc4e8e27202f40b5f0cdb_thumb.jpg',
                    'dirOne' => 'b',
                    'dirTwo' => '1b',
                ],
            ],
            'GIF Gallery' => [
                'contentUrl' => 'https://www.reddit.com/r/Terminator/comments/wvg39c/terminator_2_teaser_trailer/',
                'redditId' => 'wvg39c',
                'assets' => [
                    [
                        'sourceUrl' => 'https://preview.redd.it/hzhtz9fydej91.gif?format=mp4&v=enabled&s=47e39b0a722c44d3574d128da260a3c215812f35',
                        'filename' => '992392223d007c8fe5ea19d23097039a.mp4',
                        'dirOne' => '9',
                        'dirTwo' => '92',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/pwhjkwyxdej91.gif?format=mp4&v=enabled&s=f4d165ed609dd65758ce49f958074d4206a784b5',
                        'filename' => 'd4c3fb2adbc9b9b6496aff54a3ff4737.mp4',
                        'dirOne' => 'd',
                        'dirTwo' => '4c',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/59hsb44ydej91.gif?format=mp4&v=enabled&s=c470715029f618028026c0193a99d08ff308c1ec',
                        'filename' => 'd8daa31157e20ea933a18ff1054f9dd8.mp4',
                        'dirOne' => 'd',
                        'dirTwo' => '8d',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/h7tin1jydej91.gif?format=mp4&v=enabled&s=bfd0493433e222daa3dd0b3b2636462cd26e9bae',
                        'filename' => 'fbe9a2e4368c492f2fbf0416b813298a.mp4',
                        'dirOne' => 'f',
                        'dirTwo' => 'be',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/6b6pwxvydej91.gif?format=mp4&v=enabled&s=193a375ae0bedbbabaabf4c06704c8f8eba99881',
                        'filename' => '5f69f51086847029e25567afd0dbed44.mp4',
                        'dirOne' => '5',
                        'dirTwo' => 'f6',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/9fy58fazdej91.gif?format=mp4&v=enabled&s=99cdd935ad2a2261aaaefdaf569decfa0456d775',
                        'filename' => '9eff207f342087373f5fff7679c0549b.mp4',
                        'dirOne' => '9',
                        'dirTwo' => 'ef',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/42cnannxdej91.gif?format=mp4&v=enabled&s=eba55f272ad80243afb6397d20cdadcf876214c9',
                        'filename' => 'd1fe0c2b428e05200b21894f933f8546.mp4',
                        'dirOne' => 'd',
                        'dirTwo' => '1f',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/yvs1hq2zdej91.gif?format=mp4&v=enabled&s=8b9984580ee1a575c6aca1d2151a91ac61847afb',
                        'filename' => '8ef163c1660ca3da2067cfbff8f8a4b2.mp4',
                        'dirOne' => '8',
                        'dirTwo' => 'ef',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/lkve7ervdej91.gif?format=mp4&v=enabled&s=e30ca459633d5724ac22f39eee3b95b8b00e0bce',
                        'filename' => '2446a5bbd6b9b80358e82fe6d8a19d56.mp4',
                        'dirOne' => '2',
                        'dirTwo' => '44',
                    ],
                ],
                'thumbAsset' => [
                    'sourceUrl' => 'https://b.thumbs.redditmedia.com/J-7WpMcyHF7C8e75BZiVEgJyK6jSJLpuTJ4srJI1ojM.jpg',
                    'filename' => '5b7e653f25f3e1ac3233e510d295b7ba_thumb.jpg',
                    'dirOne' => '5',
                    'dirTwo' => 'b7',
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
        $expectedThumbPath = $this->getExpectedThumbPath($thumbAsset);
        $this->assertFileDoesNotExist($expectedThumbPath);

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
        }

        $expectedThumbPath = $this->getExpectedThumbPath($thumbAsset);
        $this->assertFileExists($expectedThumbPath);

        // Assert assets were persisted to the database and associated to the
        // intended Post.
        $mediaAssets = $post->getMediaAssets();
        $this->assertCount(count($assets), $mediaAssets);

        // Assert assets can be retrieved from the database and are
        // associated to the intended Post.
        foreach ($assets as $asset) {
            /** @var MediaAsset $mediaAsset */
            $mediaAsset = $this->entityManager
                ->getRepository(MediaAsset::class)
                ->findOneBy(['filename' => $asset['filename']])
            ;

            $this->assertEquals($asset['sourceUrl'], $mediaAsset->getSourceUrl());
            $this->assertEquals($asset['dirOne'], $mediaAsset->getDirOne());
            $this->assertEquals($asset['dirTwo'], $mediaAsset->getDirTwo());
            $this->assertEquals($post->getId(), $mediaAsset->getParentPost()->getId());

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

        $thumbnail = $post->getThumbnail();
        $this->assertEquals($thumbAsset['sourceUrl'], $thumbnail->getSourceUrl());
        $this->assertEquals($thumbAsset['filename'], $thumbnail->getFilename());
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

            $thumbAssetPath= sprintf(self::BASE_PATH_FORMAT, $targetData['thumbAsset']['dirOne'], $targetData['thumbAsset']['dirTwo']) . $targetData['thumbAsset']['filename'];
            $filesystem->remove($thumbAssetPath);
        }
    }
}
