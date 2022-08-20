<?php

namespace App\Tests\Service\Reddit;

use App\Service\Reddit\Hydrator;
use App\Service\Reddit\Manager;
use App\Service\Reddit\MediaDownloader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class MediaDownloaderTest extends KernelTestCase
{
    const ASSET_PATH_ONE = '/var/www/mra-api/public/assets/f/ac/faac0cc02f38ca7aa896f5dafdeaacb9.jpg';

    private Manager $manager;

    private MediaDownloader $mediaDownloader;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->mediaDownloader = $container->get(MediaDownloader::class);

        $this->cleanupAssets();
    }

    /**
     * https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
     *
     * @return void
     */
    public function testSaveSingleImageFromImagePost()
    {
        $redditId = 'vepbt0';
        $expectedPath = self::ASSET_PATH_ONE;

        $this->assertFileDoesNotExist($expectedPath);
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $savedPost = $this->manager->savePost($post);

        $generatedDownloadPath = $this->mediaDownloader->getFullDownloadFilePath($post);
        $this->assertEquals($expectedPath, $generatedDownloadPath);
        $this->assertFileExists($generatedDownloadPath);
    }

    public function testSaveImagesFromImageGallery()
    {
        $this->markTestSkipped();
    }

    public function testSaveImageFromTextPost()
    {
        $this->markTestSkipped();
    }

    public function testSaveGifFromPost()
    {
        $this->markTestSkipped();
    }

    public function tearDown(): void
    {
        $this->cleanupAssets();

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

        $filesystem->remove(self::ASSET_PATH_ONE);
    }
}
