<?php

namespace App\Tests\feature;

use App\Entity\FlairText;
use App\Repository\CommentRepository;
use App\Repository\FlairTextRepository;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FlairTextTest extends KernelTestCase
{
    private Manager $manager;

    private CommentRepository $commentRepository;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->commentRepository = $container->get(CommentRepository::class);
    }

    /**
     * Verify a Link Post's Flair is synced successfully.
     *
     * @return void
     */
    public function testLinkPostFlair()
    {
        $context = new Context('FlairTextTest:testLinkPostFlair');

        // Verify empty Flair Text.
        $redditId = 't3_vepbt0';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);
        $post = $content->getPost();
        $this->assertNull($post->getFlairText());


        // Verify associated Flair Text.
        $redditId = 't3_wqjpqx';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);
        $post = $content->getPost();

        $flairText = $post->getFlairText();
        $this->assertInstanceOf(FlairText::class, $flairText);
        $this->assertEquals('Discussion', $flairText->getPlainText());
        $this->assertEquals('Discussion', $flairText->getDisplayText());
    }

    /**
     * Verify a Comment's Flair is synced successfully.
     *
     * @return void
     */
    public function testCommentFlair()
    {
        $context = new Context('FlairTextTest:testCommentFlair');

        // Verify empty Flair Text.
        $redditId = 't1_ikofnq9';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);
        $comment = $this->commentRepository->findOneBy(['redditId' => 'ikofnq9']);
        $this->assertNull($comment->getFlairText());


        // Verify associated Flair Text.
        $redditId = 't1_iocadb2';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);
        $comment = $this->commentRepository->findOneBy(['redditId' => 'iocadb2']);

        $flairText = $comment->getFlairText();
        $this->assertInstanceOf(FlairText::class, $flairText);
        $this->assertEquals('“Here’s Johnny!” ', $flairText->getPlainText());
        $this->assertEquals('“Here’s Johnny!” ', $flairText->getDisplayText());
    }
}
