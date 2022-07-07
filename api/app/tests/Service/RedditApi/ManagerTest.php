<?php

namespace App\Tests\Service\RedditApi;

use App\Entity\Post;
use App\Service\RedditApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ManagerTest extends KernelTestCase
{
    private RedditApi\Manager $manager;

    private RedditApi $redditApi;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(RedditApi\Manager::class);
        $this->redditApi = $container->get(RedditApi::class);
    }

    public function testSaveImagePost()
    {
        $targetRedditId = 'vepbt0';

        $post = $this->redditApi->getPostById(RedditApi\Post::TYPE_LINK, $targetRedditId);
        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($targetRedditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
    }
}