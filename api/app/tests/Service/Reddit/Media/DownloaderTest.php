<?php

namespace App\Tests\Service\Reddit\Media;

use App\Entity\MediaAsset;
use App\Service\Reddit\Hydrator;
use App\Service\Reddit\Manager;
use App\Service\Reddit\Media\Downloader;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class DownloaderTest extends KernelTestCase
{
    const ASSET_PATH_ONE = '/var/www/mra-api/public/assets/f/aa/faac0cc02f38ca7aa896f5dafdeaacb9.jpg';

    const ASSET_PATH_TWO = '/var/www/mra-api/public/assets/9/4c/94c248fb3de02e43e46081773f5824f7.mp4';

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

        // Assert image was saved locally.
        $this->assertFileExists($expectedPath);

        $fetchedPost = $this->manager->getPostByRedditId($post->getRedditId());

        // Assert image was persisted to the database and associated to its Post.
        $mediaAssets = $fetchedPost->getMediaAssets();
        $this->assertCount(1, $mediaAssets);

        // Assert image can be retrieved from the database and is
        // associated to its Post.
        /** @var MediaAsset $mediaAsset */
        $mediaAsset = $this->entityManager
            ->getRepository(MediaAsset::class)
            ->findOneBy(['filename' => 'faac0cc02f38ca7aa896f5dafdeaacb9.jpg'])
        ;

        $this->assertEquals('https://i.imgur.com/ThRMZx5.jpg', $mediaAsset->getSourceUrl());
        $this->assertEquals('f', $mediaAsset->getDirOne());
        $this->assertEquals('aa', $mediaAsset->getDirTwo());
        $this->assertEquals($fetchedPost->getId(), $mediaAsset->getParentPost()->getId());
    }

    public function testSaveImagesFromImageGallery()
    {
        $this->markTestSkipped();
    }

    public function testSaveImageFromTextPost()
    {
        $this->markTestSkipped();
    }

    /**
     * https://www.reddit.com/r/me_irl/comments/wgb8wj/me_irl/
     *
     * @return void
     */
    public function testSaveGifFromPost()
    {
        $redditId = 'wgb8wj';
        $expectedPath = self::ASSET_PATH_TWO;

        $this->assertFileDoesNotExist($expectedPath);
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $savedPost = $this->manager->savePost($post);

        // Assert GIF was saved locally.
        $this->assertFileExists($expectedPath);

        $fetchedPost = $this->manager->getPostByRedditId($post->getRedditId());

        // Assert image was persisted to the database and associated to its Post.
        $mediaAssets = $fetchedPost->getMediaAssets();
        $this->assertCount(1, $mediaAssets);

        // Assert image can be retrieved from the database and is
        // associated to its Post.
        /** @var MediaAsset $mediaAsset */
        $mediaAsset = $this->entityManager
            ->getRepository(MediaAsset::class)
            ->findOneBy(['filename' => '94c248fb3de02e43e46081773f5824f7.mp4'])
        ;

        $this->assertEquals('https://preview.redd.it/kanpjvgbarf91.gif?format=mp4&s=d3c0bb16145d61e9872bda355b742cfd3031fd69', $mediaAsset->getSourceUrl());
        $this->assertEquals('9', $mediaAsset->getDirOne());
        $this->assertEquals('4c', $mediaAsset->getDirTwo());
        $this->assertEquals($fetchedPost->getId(), $mediaAsset->getParentPost()->getId());
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

        $paths = [
            self::ASSET_PATH_ONE,
            self::ASSET_PATH_TWO,
        ];

        foreach ($paths as $path) {
            $filesystem->remove($path);
        }
    }
}
