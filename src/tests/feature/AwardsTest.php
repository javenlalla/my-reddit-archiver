<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Entity\Kind;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AwardsTest extends KernelTestCase
{
    private Manager $manager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
    }

    /**
     * https://www.reddit.com/r/Jokes/comments/y1vmdf/the_indian_restaurant_i_work_for_is_so_secretive/
     *
     * @return void
     */
    public function testGetPostAwards()
    {
        $context = new Context('AwardsTest:testGetPostAwards');
        $postUrl = 'https://www.reddit.com/r/Jokes/comments/y1vmdf/the_indian_restaurant_i_work_for_is_so_secretive/';

        $content = $this->manager->syncContentFromJsonUrl($context, Kind::KIND_LINK, $postUrl);
        $post = $content->getPost();

        // Verify each type of expected Award has been persisted and associated.
        $this->assertCount(6, $post->getPostAwards());
        $this->assertEquals(17, $post->getPostAwardsTrueCount());

        // Verify if the same Post is synced, Awards are not duplicated.
        $content = $this->manager->syncContentFromJsonUrl($context, Kind::KIND_LINK, $postUrl);
        $post = $content->getPost();

        $this->assertCount(6, $post->getPostAwards());
        $this->assertEquals(17, $post->getPostAwardsTrueCount());
    }

    /**
     * https://www.reddit.com/r/Jokes/comments/y1vmdf/comment/is022vs
     *
     * @return void
     */
    public function testGetCommentAwards()
    {
        $context = new Context('AwardsTest:testGetCommentAwards');
        $commentUrl = 'https://www.reddit.com/r/Jokes/comments/y1vmdf/comment/is022vs';

        $content = $this->manager->syncContentFromJsonUrl($context, Kind::KIND_COMMENT, $commentUrl);
        $comment = $content->getComment();

        // Verify each type of expected Award has been persisted and associated.
        $this->assertCount(2, $comment->getCommentAwards());
        $this->assertEquals(2, $comment->getCommentAwardsTrueCount());

        // Verify if the same Comment is synced, Awards are not duplicated.
        $content = $this->manager->syncContentFromJsonUrl($context, Kind::KIND_COMMENT, $commentUrl);
        $comment = $content->getComment();

        $this->assertCount(2, $comment->getCommentAwards());
        $this->assertEquals(2, $comment->getCommentAwardsTrueCount());
    }
}
