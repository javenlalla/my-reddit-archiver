<?php

namespace App\Tests\Service\Reddit\Media;

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
    const BASE_PATH_FORMAT = '/var/www/mra/public/assets/%s/%s/';

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
                        'sourceUrl' => 'https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&s=7cab4910712115bb273171653cc754b9077c1455',
                        'filename' => '0a6f67fe20592b9c659e7deee5efe877.jpg',
                        'dirOne' => '0',
                        'dirTwo' => 'a6',
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
                        'sourceUrl' => 'https://preview.redd.it/kanpjvgbarf91.gif?format=mp4&s=d3c0bb16145d61e9872bda355b742cfd3031fd69',
                        'filename' => '1aeefb8b0eb681ac3aaa5ee8e4fd2bcb.mp4',
                        'dirOne' => '1',
                        'dirTwo' => 'ae',
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
                        'sourceUrl' => 'https://preview.redd.it/zy4xzki4jx291.jpg?width=2543&format=pjpg&auto=webp&s=2f4c3f05a428019b6754ca3c9ab8d3122df14664',
                        'filename' => 'abe4e7c93ae266ca7d6043c4f8a82c5d.jpg',
                        'dirOne' => 'a',
                        'dirTwo' => 'be',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/exunuhm4jx291.jpg?width=612&format=pjpg&auto=webp&s=1aadfb05549500b4a3e61f377a87b6739d7e92e7',
                        'filename' => 'd3961edaeaef4913869b6d30e4472d1a.jpg',
                        'dirOne' => 'd',
                        'dirTwo' => '39',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/rs5yhje4jx291.jpg?width=1080&format=pjpg&auto=webp&s=d6d30ce00bf261edf76802fd79a455ad08bc0d62',
                        'filename' => '9676a19295d2317fdd111c28324d438b.jpg',
                        'dirOne' => '9',
                        'dirTwo' => '67',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/s0yrptf4jx291.jpg?width=612&format=pjpg&auto=webp&s=b7442ac83a19780a34ababb9439ef857a672a13f',
                        'filename' => '4b59d9f517130e6233d5e7982ee97376.jpg',
                        'dirOne' => '4',
                        'dirTwo' => 'b5',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/jpmunxg4jx291.jpg?width=1080&format=pjpg&auto=webp&s=0ea1e60464a6905e72f06a70c4e781ec16ac0af6',
                        'filename' => '901411feb2aaa0f697396cf1c0caadfe.jpg',
                        'dirOne' => '9',
                        'dirTwo' => '01',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/6p3g7c64jx291.jpg?width=2543&format=pjpg&auto=webp&s=5914dc1cd03aa246d5a22810bf64098674092691',
                        'filename' => '7d0f3d94afea696aeaf6b8b6d6e5ee15.jpg',
                        'dirOne' => '7',
                        'dirTwo' => 'd0',
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
                        'sourceUrl' => 'https://preview.redd.it/hzhtz9fydej91.gif?format=mp4&s=43a197453fe9eebf82404c643507ed622f9760e4',
                        'filename' => '7aa0a5546105afba1c31947897880dba.mp4',
                        'dirOne' => '7',
                        'dirTwo' => 'aa',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/pwhjkwyxdej91.gif?format=mp4&s=25ac9c9a6dc03ad3d7ef36f859c13f5edcde08fb',
                        'filename' => '6c82276ca3b65eb70fdbe7c149d95023.mp4',
                        'dirOne' => '6',
                        'dirTwo' => 'c8',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/59hsb44ydej91.gif?format=mp4&s=77fff215f5af86ce035b0d05de9ca66649458ebc',
                        'filename' => 'e0d5057f173251a71ae3319b53c55c7c.mp4',
                        'dirOne' => 'e',
                        'dirTwo' => '0d',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/h7tin1jydej91.gif?format=mp4&s=4eb0e10b22e5e6962c2f58bf57e7f78ab8dab98d',
                        'filename' => '532770974cf94176ab9fccca2c895a17.mp4',
                        'dirOne' => '5',
                        'dirTwo' => '32',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/lkve7ervdej91.gif?format=mp4&s=5a76bc4c82dcb15cb9d23dc6f62eb4c65e424598',
                        'filename' => 'eb7508f732614348dcb4a64dea720824.mp4',
                        'dirOne' => 'e',
                        'dirTwo' => 'b7',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/9fy58fazdej91.gif?format=mp4&s=d7f53d9e580e2520acd7a02bd22db1d645249141',
                        'filename' => 'c955af9f84d1906e8c3766fdd7bc889d.mp4',
                        'dirOne' => 'c',
                        'dirTwo' => '95',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/42cnannxdej91.gif?format=mp4&s=7376b9c6327d07dbfbc2b23e903f0a0b8e28e559',
                        'filename' => 'a9d328a856f6a16f3047f1072ab369a0.mp4',
                        'dirOne' => 'a',
                        'dirTwo' => '9d',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/yvs1hq2zdej91.gif?format=mp4&s=91d6ca9b40ba839f9d16b5f187332646df4047a4',
                        'filename' => 'ff96a712f2417f1b551bcb80e3093e78.mp4',
                        'dirOne' => 'f',
                        'dirTwo' => 'f9',
                    ],
                    [
                        'sourceUrl' => 'https://preview.redd.it/6b6pwxvydej91.gif?format=mp4&s=22b28c51afe45f9586f83a2d722522154704b62b',
                        'filename' => 'e1700a5bc0cd6f102b67b8ad3ead6700.mp4',
                        'dirOne' => 'e',
                        'dirTwo' => '17',
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
