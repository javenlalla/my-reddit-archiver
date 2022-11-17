<?php

namespace App\Tests\Service\Reddit\Media;

use App\Entity\Kind;
use App\Entity\MediaAsset;
use App\Service\Reddit\Manager;
use App\Service\Reddit\Media\Downloader;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class DownloaderTest extends KernelTestCase
{
    const BASE_PATH_FORMAT = '/var/www/mra-api/public/assets/%s/%s/';

    const ASSET_IMAGE_PATH = '/var/www/mra-api/public/assets/f/aa/faac0cc02f38ca7aa896f5dafdeaacb9.jpg';

    const ASSET_IMAGE_THUMB_PATH = '/var/www/mra-api/public/assets/c/d7/cd79c58c96cbc4684f4aef775c47f5a5_thumb.jpg';

    const ASSET_REDDIT_HOSTED_IMAGE_PATH = '/var/www/mra-api/public/assets/4/4c/44cdd5b77a44b3ebd1e955946e71efc0.jpg';

    const ASSET_REDDIT_HOSTED_IMAGE_THUMB_PATH = '/var/www/mra-api/public/assets/5/f1/5f1ff800d8d3dbdec298e3969b5fcbd2_thumb.jpg';

    const ASSET_TEXT_WITH_IMAGE_PATH = '/var/www/mra-api/public/assets/0/a6/0a6f67fe20592b9c659e7deee5efe877.jpg';

    const ASSET_TEXT_WITH_IMAGE_THUMB_PATH = '/var/www/mra-api/public/assets/5/a5/5a5859e3f92e5fb89c5971666c37a682_thumb.jpg';

    const ASSET_GIF_PATH = '/var/www/mra-api/public/assets/1/ae/1aeefb8b0eb681ac3aaa5ee8e4fd2bcb.mp4';

    const ASSET_GIF_THUMB_PATH = '/var/www/mra-api/public/assets/f/e2/fe2b035aaeed849079008231923cf160_thumb.jpg';

    const ASSET_REDDIT_VIDEO_PATH = '/var/www/mra-api/public/assets/a/01/a01b41d34f5bb8bceb7540fa1b84728a.mp4';

    const ASSET_REDDIT_VIDEO_THUMB_PATH = '/var/www/mra-api/public/assets/e/70/e70ab5ad74e5a52e6d1f14d92b7f2187_thumb.jpg';

    const ASSET_REDDIT_VIDEO_NO_AUDIO_PATH = '/var/www/mra-api/public/assets/1/7d/17de4f10fe97940aba8170d1eec6caf0.mp4';

    const ASSET_REDDIT_VIDEO_NO_AUDIO_THUMB_PATH = '/var/www/mra-api/public/assets/7/15/7151e2d464c3e108fc921134cd003d25_thumb.jpg';

    const IMAGE_GALLERY_ASSETS = [
        [
            'filename' => 'abe4e7c93ae266ca7d6043c4f8a82c5d.jpg',
            'dirOne' => 'a',
            'dirTwo' => 'be',
            'filePath' => '/var/www/mra-api/public/assets/a/be/abe4e7c93ae266ca7d6043c4f8a82c5d.jpg',
            'sourceUrl' => 'https://preview.redd.it/zy4xzki4jx291.jpg?width=2543&format=pjpg&auto=webp&s=2f4c3f05a428019b6754ca3c9ab8d3122df14664',
        ],
        [
            'filename' => 'd3961edaeaef4913869b6d30e4472d1a.jpg',
            'dirOne' => 'd',
            'dirTwo' => '39',
            'filePath' => '/var/www/mra-api/public/assets/d/39/d3961edaeaef4913869b6d30e4472d1a.jpg',
            'sourceUrl' => 'https://preview.redd.it/exunuhm4jx291.jpg?width=612&format=pjpg&auto=webp&s=1aadfb05549500b4a3e61f377a87b6739d7e92e7',
        ],
        [
            'filename' => '9676a19295d2317fdd111c28324d438b.jpg',
            'dirOne' => '9',
            'dirTwo' => '67',
            'filePath' => '/var/www/mra-api/public/assets/9/67/9676a19295d2317fdd111c28324d438b.jpg',
            'sourceUrl' => 'https://preview.redd.it/rs5yhje4jx291.jpg?width=1080&format=pjpg&auto=webp&s=d6d30ce00bf261edf76802fd79a455ad08bc0d62',
        ],
        [
            'filename' => '4b59d9f517130e6233d5e7982ee97376.jpg',
            'dirOne' => '4',
            'dirTwo' => 'b5',
            'filePath' => '/var/www/mra-api/public/assets/4/b5/4b59d9f517130e6233d5e7982ee97376.jpg',
            'sourceUrl' => 'https://preview.redd.it/s0yrptf4jx291.jpg?width=612&format=pjpg&auto=webp&s=b7442ac83a19780a34ababb9439ef857a672a13f',
        ],
        [
            'filename' => '901411feb2aaa0f697396cf1c0caadfe.jpg',
            'dirOne' => '9',
            'dirTwo' => '01',
            'filePath' => '/var/www/mra-api/public/assets/9/01/901411feb2aaa0f697396cf1c0caadfe.jpg',
            'sourceUrl' => 'https://preview.redd.it/jpmunxg4jx291.jpg?width=1080&format=pjpg&auto=webp&s=0ea1e60464a6905e72f06a70c4e781ec16ac0af6',
        ],
        [
            'filename' => '7d0f3d94afea696aeaf6b8b6d6e5ee15.jpg',
            'dirOne' => '7',
            'dirTwo' => 'd0',
            'filePath' => '/var/www/mra-api/public/assets/7/d0/7d0f3d94afea696aeaf6b8b6d6e5ee15.jpg',
            'sourceUrl' => 'https://preview.redd.it/6p3g7c64jx291.jpg?width=2543&format=pjpg&auto=webp&s=5914dc1cd03aa246d5a22810bf64098674092691',
        ],
    ];

    const IMAGE_GALLERY_THUMB_PATH = '/var/www/mra-api/public/assets/b/1b/b1b23689fb7fc4e8e27202f40b5f0cdb_thumb.jpg';

    const IMAGE_GALLERY_GIF_ASSETS = [
        [
            'filename' => '7aa0a5546105afba1c31947897880dba.mp4',
            'thumbFilename' => '7aa0a5546105afba1c31947897880dba_thumb.jpg',
            'dirOne' => '7',
            'dirTwo' => 'aa',
            'filePath' => '/var/www/mra-api/public/assets/7/aa/7aa0a5546105afba1c31947897880dba.mp4',
            'thumbFilePath' => '/var/www/mra-api/public/assets/7/aa/7aa0a5546105afba1c31947897880dba_thumb.jpg',
            'sourceUrl' => 'https://preview.redd.it/hzhtz9fydej91.gif?format=mp4&s=43a197453fe9eebf82404c643507ed622f9760e4',
        ],
        [
            'filename' => '6c82276ca3b65eb70fdbe7c149d95023.mp4',
            'thumbFilename' => '6c82276ca3b65eb70fdbe7c149d95023_thumb.jpg',
            'dirOne' => '6',
            'dirTwo' => 'c8',
            'filePath' => '/var/www/mra-api/public/assets/6/c8/6c82276ca3b65eb70fdbe7c149d95023.mp4',
            'thumbFilePath' => '/var/www/mra-api/public/assets/6/c8/6c82276ca3b65eb70fdbe7c149d95023_thumb.jpg',
            'sourceUrl' => 'https://preview.redd.it/pwhjkwyxdej91.gif?format=mp4&s=25ac9c9a6dc03ad3d7ef36f859c13f5edcde08fb',
        ],
        [
            'filename' => 'e0d5057f173251a71ae3319b53c55c7c.mp4',
            'thumbFilename' => 'e0d5057f173251a71ae3319b53c55c7c_thumb.jpg',
            'dirOne' => 'e',
            'dirTwo' => '0d',
            'filePath' => '/var/www/mra-api/public/assets/e/0d/e0d5057f173251a71ae3319b53c55c7c.mp4',
            'thumbFilePath' => '/var/www/mra-api/public/assets/e/0d/e0d5057f173251a71ae3319b53c55c7c_thumb.jpg',
            'sourceUrl' => 'https://preview.redd.it/59hsb44ydej91.gif?format=mp4&s=77fff215f5af86ce035b0d05de9ca66649458ebc',
        ],
        [
            'filename' => '532770974cf94176ab9fccca2c895a17.mp4',
            'thumbFilename' => '532770974cf94176ab9fccca2c895a17_thumb.jpg',
            'dirOne' => '5',
            'dirTwo' => '32',
            'filePath' => '/var/www/mra-api/public/assets/5/32/532770974cf94176ab9fccca2c895a17.mp4',
            'thumbFilePath' => '/var/www/mra-api/public/assets/5/32/532770974cf94176ab9fccca2c895a17_thumb.jpg',
            'sourceUrl' => 'https://preview.redd.it/h7tin1jydej91.gif?format=mp4&s=4eb0e10b22e5e6962c2f58bf57e7f78ab8dab98d',
        ],
        [
            'filename' => 'eb7508f732614348dcb4a64dea720824.mp4',
            'thumbFilename' => 'eb7508f732614348dcb4a64dea720824_thumb.jpg',
            'dirOne' => 'e',
            'dirTwo' => 'b7',
            'filePath' => '/var/www/mra-api/public/assets/e/b7/eb7508f732614348dcb4a64dea720824.mp4',
            'thumbFilePath' => '/var/www/mra-api/public/assets/e/b7/eb7508f732614348dcb4a64dea720824_thumb.jpg',
            'sourceUrl' => 'https://preview.redd.it/lkve7ervdej91.gif?format=mp4&s=5a76bc4c82dcb15cb9d23dc6f62eb4c65e424598',
        ],
        [
            'filename' => 'c955af9f84d1906e8c3766fdd7bc889d.mp4',
            'thumbFilename' => 'c955af9f84d1906e8c3766fdd7bc889d_thumb.jpg',
            'dirOne' => 'c',
            'dirTwo' => '95',
            'filePath' => '/var/www/mra-api/public/assets/c/95/c955af9f84d1906e8c3766fdd7bc889d.mp4',
            'thumbFilePath' => '/var/www/mra-api/public/assets/c/95/c955af9f84d1906e8c3766fdd7bc889d_thumb.jpg',
            'sourceUrl' => 'https://preview.redd.it/9fy58fazdej91.gif?format=mp4&s=d7f53d9e580e2520acd7a02bd22db1d645249141',
        ],
        [
            'filename' => 'a9d328a856f6a16f3047f1072ab369a0.mp4',
            'thumbFilename' => 'a9d328a856f6a16f3047f1072ab369a0_thumb.jpg',
            'dirOne' => 'a',
            'dirTwo' => '9d',
            'filePath' => '/var/www/mra-api/public/assets/a/9d/a9d328a856f6a16f3047f1072ab369a0.mp4',
            'thumbFilePath' => '/var/www/mra-api/public/assets/a/9d/a9d328a856f6a16f3047f1072ab369a0_thumb.jpg',
            'sourceUrl' => 'https://preview.redd.it/42cnannxdej91.gif?format=mp4&s=7376b9c6327d07dbfbc2b23e903f0a0b8e28e559',
        ],
        [
            'filename' => 'ff96a712f2417f1b551bcb80e3093e78.mp4',
            'thumbFilename' => 'ff96a712f2417f1b551bcb80e3093e78_thumb.jpg',
            'dirOne' => 'f',
            'dirTwo' => 'f9',
            'filePath' => '/var/www/mra-api/public/assets/f/f9/ff96a712f2417f1b551bcb80e3093e78.mp4',
            'thumbFilePath' => '/var/www/mra-api/public/assets/f/f9/ff96a712f2417f1b551bcb80e3093e78_thumb.jpg',
            'sourceUrl' => 'https://preview.redd.it/yvs1hq2zdej91.gif?format=mp4&s=91d6ca9b40ba839f9d16b5f187332646df4047a4',
        ],
        [
            'filename' => 'e1700a5bc0cd6f102b67b8ad3ead6700.mp4',
            'thumbFilename' => 'e1700a5bc0cd6f102b67b8ad3ead6700_thumb.jpg',
            'dirOne' => 'e',
            'dirTwo' => '17',
            'filePath' => '/var/www/mra-api/public/assets/e/17/e1700a5bc0cd6f102b67b8ad3ead6700.mp4',
            'thumbFilePath' => '/var/www/mra-api/public/assets/e/17/e1700a5bc0cd6f102b67b8ad3ead6700_thumb.jpg',
            'sourceUrl' => 'https://preview.redd.it/6b6pwxvydej91.gif?format=mp4&s=22b28c51afe45f9586f83a2d722522154704b62b',
        ],
    ];

    const IMAGE_GALLERY_GIF_THUMB_PATH = '/var/www/mra-api/public/assets/5/b7/5b7e653f25f3e1ac3233e510d295b7ba_thumb.jpg';

    private Manager $manager;

    private Downloader $mediaDownloader;

    private EntityManager $entityManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->mediaDownloader = $container->get(Downloader::class);
        $this->entityManager = $container->get('doctrine')->getManager();

        $this->cleanupAssets();
    }

    /**
     * @dataProvider saveAssetsFromPostsDataProvider
     *
     * @return void
     */
    public function testSaveAssetsFromPosts(string $redditId, string $sourceUrl, string $filename, string $dirOne, string $dirTwo, string $thumbSourceUrl, string $thumbFilename, string $thumbDirOne, string $thumbDirTwo, string $audioSourceUrl = null, string $audioFilename = null)
    {
        $expectedPath = sprintf(self::BASE_PATH_FORMAT, $dirOne, $dirTwo) . $filename;
        $expectedThumbPath= sprintf(self::BASE_PATH_FORMAT, $thumbDirOne, $thumbDirTwo) . $thumbFilename;
        $this->assertFileDoesNotExist($expectedPath);
        $this->assertFileDoesNotExist($expectedThumbPath);

        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_LINK . '_' . $redditId);
        $post = $content->getPost();

        // Assert asset was saved locally.
        $this->assertFileExists($expectedPath);
        $this->assertFileExists($expectedThumbPath);

        // Assert asset was persisted to the database and associated to its Post.
        $mediaAssets = $post->getMediaAssets();
        $this->assertCount(1, $mediaAssets);

        // Assert asset can be retrieved from the database and is
        // associated to its Post.
        /** @var MediaAsset $mediaAsset */
        $mediaAsset = $this->entityManager
            ->getRepository(MediaAsset::class)
            ->findOneBy(['filename' => $filename])
        ;

        $this->assertEquals($sourceUrl, $mediaAsset->getSourceUrl());
        $this->assertEquals($dirOne, $mediaAsset->getDirOne());
        $this->assertEquals($dirTwo, $mediaAsset->getDirTwo());
        $this->assertEquals($post->getId(), $mediaAsset->getParentPost()->getId());

        if (!empty($audioSourceUrl)) {
            $this->assertEquals($audioSourceUrl, $mediaAsset->getAudioSourceUrl());
        } else {
            $this->assertEmpty($mediaAsset->getAudioSourceUrl());
        }

        if (!empty($audioFilename)) {
            $this->assertEquals($audioFilename, $mediaAsset->getAudioFilename());
        } else {
            $this->assertEmpty($mediaAsset->getAudioFilename());
        }

        $thumbnail = $post->getThumbnail();
        $this->assertEquals($thumbSourceUrl, $thumbnail->getSourceUrl());
        $this->assertEquals($thumbFilename, $thumbnail->getFilename());
    }

    public function saveAssetsFromPostsDataProvider()
    {
        return [
            [
                // https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
                'redditId' => 'vepbt0',
                'sourceUrl' => 'https://i.imgur.com/ThRMZx5.jpg',
                'filename' => 'faac0cc02f38ca7aa896f5dafdeaacb9.jpg',
                'dirOne' => 'f',
                'dirTwo' => 'aa',
                'thumbSourceUrl' => 'https://b.thumbs.redditmedia.com/eVhpmEiR3ItbKk6R0SDI6C1XM5ONek_xcQIIhtCA5YQ.jpg',
                'thumbFilename' => 'cd79c58c96cbc4684f4aef775c47f5a5_thumb.jpg',
                'thumbDirOne' => 'c',
                'thumbDirTwo' => 'd7',
            ],
            [
                // https://www.reddit.com/r/coolguides/comments/won0ky/i_learned_how_to_whistle_from_this_in_less_than_5/
                'redditId' => 'won0ky',
                'sourceUrl' => 'https://i.redd.it/cnfk33iv9sh91.jpg',
                'filename' => '44cdd5b77a44b3ebd1e955946e71efc0.jpg',
                'dirOne' => '4',
                'dirTwo' => '4c',
                'thumbSourceUrl' => 'https://b.thumbs.redditmedia.com/_9QxeKKVgR-o6E9JE-vydP1i5OpkyEziomCERjBlSOU.jpg',
                'thumbFilename' => '5f1ff800d8d3dbdec298e3969b5fcbd2_thumb.jpg',
                'thumbDirOne' => '5',
                'thumbDirTwo' => 'f1',
            ],
            [
                // https://www.reddit.com/r/Tremors/comments/utsmkw/tremors_poster_for_gallery1988
                'redditId' => 'utsmkw',
                'sourceUrl' => 'https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&s=7cab4910712115bb273171653cc754b9077c1455',
                'filename' => '0a6f67fe20592b9c659e7deee5efe877.jpg',
                'dirOne' => '0',
                'dirTwo' => 'a6',
                'thumbSourceUrl' => 'https://b.thumbs.redditmedia.com/q06gPIAKixPJS38j1dkiwiEqiA6k4kqie84T5yLgt4o.jpg',
                'thumbFilename' => '5a5859e3f92e5fb89c5971666c37a682_thumb.jpg',
                'thumbDirOne' => '5',
                'thumbDirTwo' => 'a5',
            ],
            [
                // https://www.reddit.com/r/me_irl/comments/wgb8wj/me_irl/
                'redditId' => 'wgb8wj',
                'sourceUrl' => 'https://preview.redd.it/kanpjvgbarf91.gif?format=mp4&s=d3c0bb16145d61e9872bda355b742cfd3031fd69',
                'filename' => '1aeefb8b0eb681ac3aaa5ee8e4fd2bcb.mp4',
                'dirOne' => '1',
                'dirTwo' => 'ae',
                'thumbSourceUrl' => 'https://a.thumbs.redditmedia.com/DI9yoWanjzCXyy5kF8-JFfP-SPg2__nhBo0HNSxU8W4.jpg',
                'thumbFilename' => 'fe2b035aaeed849079008231923cf160_thumb.jpg',
                'thumbDirOne' => 'f',
                'thumbDirTwo' => 'e2',
            ],
            [
                // @TODO: Add initial assertion to ensure ffmpeg is installed.
                // https://www.reddit.com/r/Unexpected/comments/tl8qic/i_think_i_married_a_psychopath/
                'redditId' => 'tl8qic',
                'sourceUrl' => 'https://v.redd.it/8u3caw3zm6p81/DASH_720.mp4?source=fallback',
                'filename' => 'a01b41d34f5bb8bceb7540fa1b84728a.mp4',
                'dirOne' => 'a',
                'dirTwo' => '01',
                'thumbSourceUrl' => 'https://b.thumbs.redditmedia.com/CPQpNEdyLw1Q2bK0jIpY8dLUtLzmegTqKJQMp5ONxto.jpg',
                'thumbFilename' => 'e70ab5ad74e5a52e6d1f14d92b7f2187_thumb.jpg',
                'thumbDirOne' => 'e',
                'thumbDirTwo' => '70',
                'audioSourceUrl' => 'https://v.redd.it/8u3caw3zm6p81/DASH_audio.mp4',
                'audioFilename' => '8u3caw3zm6p81_audio.mp4',
            ],
            [
                /**
                 * Validate persisting a Reddit-hosted Video that does not contain audio.
                 *
                 * Only the Video file (.mp4) should be downloaded and no Audio properties
                 * should be set.
                 *
                 * https://www.reddit.com/r/ProgrammerHumor/comments/wfylnl/when_you_use_a_new_library_without_reading_the/
                 */
                'redditId' => 'wfylnl',
                'sourceUrl' => 'https://v.redd.it/bofh9q9jkof91/DASH_720.mp4?source=fallback',
                'filename' => '17de4f10fe97940aba8170d1eec6caf0.mp4',
                'dirOne' => '1',
                'dirTwo' => '7d',
                'thumbSourceUrl' => 'https://b.thumbs.redditmedia.com/EP5Wgd7mgrsKVgPOFgiAvDblLmm5qNSBnSAvqzAZFcE.jpg',
                'thumbFilename' => '7151e2d464c3e108fc921134cd003d25_thumb.jpg',
                'thumbDirOne' => '7',
                'thumbDirTwo' => '15',
            ],
        ];
    }

    /**
     * https://www.reddit.com/r/Tremors/comments/v27nr7/all_my_recreations_of_magazine_covers_from/
     *
     * @return void
     */
    public function testSaveImagesFromImageGallery()
    {
        $redditId = 'v27nr7';
        $expectedThumbPath = self::IMAGE_GALLERY_THUMB_PATH;
        $this->assertFileDoesNotExist($expectedThumbPath);

        foreach (self::IMAGE_GALLERY_ASSETS as $galleryAsset) {
            $this->assertFileDoesNotExist($galleryAsset['filePath']);
        }

        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_LINK . '_' . $redditId);
        $post = $content->getPost();

        // Assert assets were saved locally.
        $this->assertFileExists($expectedThumbPath);
        foreach (self::IMAGE_GALLERY_ASSETS as $galleryAsset) {
            $this->assertFileExists($galleryAsset['filePath']);
        }

        // Assert assets were persisted to the database and associated to this
        // current Post.
        $mediaAssets = $post->getMediaAssets();
        $this->assertCount(6, $mediaAssets);

        // Assert assets can be retrieved from the database and are
        // associated to this current Post.
        foreach (self::IMAGE_GALLERY_ASSETS as $galleryAsset) {
            /** @var MediaAsset $mediaAsset */
            $mediaAsset = $this->entityManager
                ->getRepository(MediaAsset::class)
                ->findOneBy(['filename' => $galleryAsset['filename']])
            ;

            $this->assertEquals($galleryAsset['sourceUrl'], $mediaAsset->getSourceUrl());
            $this->assertEquals($galleryAsset['dirOne'], $mediaAsset->getDirOne());
            $this->assertEquals($galleryAsset['dirTwo'], $mediaAsset->getDirTwo());
            $this->assertEquals($post->getId(), $mediaAsset->getParentPost()->getId());
        }

        $thumbnail = $post->getThumbnail();
        $this->assertEquals('https://a.thumbs.redditmedia.com/oicSvcPsUxSfSzil8Hh7b1QD1T_GJq_vIo7iFtrkDd0.jpg', $thumbnail->getSourceUrl());
        $this->assertEquals('b1b23689fb7fc4e8e27202f40b5f0cdb_thumb.jpg', $thumbnail->getFilename());
    }

    /**
     * https://www.reddit.com/r/Terminator/comments/wvg39c/terminator_2_teaser_trailer/
     *
     * @return void
     */
    public function testSaveGifsFromImageGallery()
    {
        $redditId = 'wvg39c';
        $expectedThumbPath = self::IMAGE_GALLERY_GIF_THUMB_PATH;
        $this->assertFileDoesNotExist($expectedThumbPath);

        foreach (self::IMAGE_GALLERY_GIF_ASSETS as $galleryAsset) {
            $this->assertFileDoesNotExist($galleryAsset['filePath']);
        }

        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_LINK . '_' . $redditId);
        $post = $content->getPost();

        // Assert assets were saved locally.
        $this->assertFileExists($expectedThumbPath);
        foreach (self::IMAGE_GALLERY_GIF_ASSETS as $galleryAsset) {
            $this->assertFileExists($galleryAsset['filePath']);
        }

        // Assert assets were persisted to the database and associated to this
        // current Post.
        $mediaAssets = $post->getMediaAssets();
        $this->assertCount(9, $mediaAssets);

        // Assert assets can be retrieved from the database and are
        // associated to this current Post.
        foreach (self::IMAGE_GALLERY_GIF_ASSETS as $galleryAsset) {
            /** @var MediaAsset $mediaAsset */
            $mediaAsset = $this->entityManager
                ->getRepository(MediaAsset::class)
                ->findOneBy(['filename' => $galleryAsset['filename']])
            ;

            $this->assertEquals($galleryAsset['sourceUrl'], $mediaAsset->getSourceUrl());
            $this->assertEquals($galleryAsset['dirOne'], $mediaAsset->getDirOne());
            $this->assertEquals($galleryAsset['dirTwo'], $mediaAsset->getDirTwo());
            $this->assertEquals($post->getId(), $mediaAsset->getParentPost()->getId());
        }

        $thumbnail = $post->getThumbnail();
        $this->assertEquals('https://b.thumbs.redditmedia.com/J-7WpMcyHF7C8e75BZiVEgJyK6jSJLpuTJ4srJI1ojM.jpg', $thumbnail->getSourceUrl());
        $this->assertEquals('5b7e653f25f3e1ac3233e510d295b7ba_thumb.jpg', $thumbnail->getFilename());
    }

    public function tearDown(): void
    {
        $this->cleanupAssets();
        $this->cleanupAssetsV2();

        parent::tearDown();
    }

    /**
     * Delete any asset files that may already exist from previous testing.
     *
     * @return void
     */
    private function cleanupAssets(): void
    {
        $filesystem = new Filesystem();

        $paths = [
            self::ASSET_IMAGE_PATH,
            self::ASSET_IMAGE_THUMB_PATH,
            self::ASSET_REDDIT_HOSTED_IMAGE_PATH,
            self::ASSET_REDDIT_HOSTED_IMAGE_THUMB_PATH,
            self::ASSET_GIF_PATH,
            self::ASSET_GIF_THUMB_PATH,
            self::ASSET_TEXT_WITH_IMAGE_PATH,
            self::ASSET_TEXT_WITH_IMAGE_THUMB_PATH,
            self::ASSET_REDDIT_VIDEO_PATH,
            self::ASSET_REDDIT_VIDEO_THUMB_PATH,
            self::ASSET_REDDIT_VIDEO_NO_AUDIO_PATH,
            self::ASSET_REDDIT_VIDEO_NO_AUDIO_THUMB_PATH,
            self::IMAGE_GALLERY_THUMB_PATH,
            self::IMAGE_GALLERY_GIF_THUMB_PATH,
        ];

        foreach ($paths as $path) {
            $filesystem->remove($path);
        }

        foreach (self::IMAGE_GALLERY_ASSETS as $galleryAsset) {
            $filesystem->remove($galleryAsset['filePath']);
        }

        foreach (self::IMAGE_GALLERY_GIF_ASSETS as $galleryAsset) {
            $filesystem->remove($galleryAsset['filePath']);
        }
    }

    private function cleanupAssetsV2()
    {
        $filesystem = new Filesystem();

        $targetDataArray = $this->saveAssetsFromPostsDataProvider();
        foreach ($targetDataArray as $targetData) {
            $assetPath = sprintf(self::BASE_PATH_FORMAT, $targetData['dirOne'], $targetData['dirTwo']) . $targetData['filename'];
            $thumbAssetPath= sprintf(self::BASE_PATH_FORMAT, $targetData['thumbDirOne'], $targetData['thumbDirTwo']) . $targetData['thumbFilename'];

            $filesystem->remove($assetPath);
            $filesystem->remove($thumbAssetPath);
        }
    }
}
