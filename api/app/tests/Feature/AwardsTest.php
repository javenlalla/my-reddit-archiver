<?php

namespace App\Tests\Feature;

use App\Entity\AuthorText;
use App\Entity\Comment;
use App\Entity\CommentAuthorText;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Service\Reddit\Manager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        $postUrl = 'https://www.reddit.com/r/Jokes/comments/y1vmdf/the_indian_restaurant_i_work_for_is_so_secretive/';

        $content = $this->manager->syncContentFromJsonUrl(Kind::KIND_LINK, $postUrl);
        $post = $content->getPost();

        // Verify each type of expected Award has been persisted and associated.
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

    }
}
