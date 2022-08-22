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
    const ASSET_PATH_ONE = '/var/www/mra-api/public/assets/f/ac/faac0cc02f38ca7aa896f5dafdeaacb9.jpg';

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

        $this->assertEquals('f', $mediaAsset->getDirOne());
        $this->assertEquals('ac', $mediaAsset->getDirTwo());
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
