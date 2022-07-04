<?php

namespace App\Tests\Service;

use App\Service\RedditApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RedditApiTest extends KernelTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
    }

    public function testArticleComments()
    {
        $postId = 'vlyukg';

        // (1) boot the Symfony kernel
        // self::bootKernel();

        // (2) use static::getContainer() to access the service container
        $container = static::getContainer();

        // (3) run some service & test the result
        // $newsletterGenerator = $container->get(NewsletterGenerator::class);
        // $newsletter = $newsletterGenerator->generateMonthlyNews(...);

        /** @var RedditApi $redditApi */
        $redditApi = $container->get(RedditApi::class);
        $comments = $redditApi->getCommentsByPostId($postId);

        // $this->assertEquals('...', $newsletter->getContent());
        $this->assertNotEmpty($comments);
    }
}