<?php

namespace App\Tests\Service\Reddit;

use App\Entity\Comment;
use App\Entity\ContentType;
use App\Entity\Post;
use App\Entity\Type;
use App\Service\Reddit\Hydrator;
use App\Service\Reddit\Manager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ManagerTest extends KernelTestCase
{
    private Manager $manager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
    }

    public function testGetPostFromApiByRedditId()
    {
        $redditId = 'vepbt0';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals($redditId, $post->getRedditId());
        $this->assertEquals('My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…', $post->getTitle());
        $this->assertEquals('https://i.imgur.com/ThRMZx5.jpg', $post->getUrl());
    }

    /**
     * https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
     *
     * @return void
     */
    public function testSaveImagePost()
    {
        $redditId = 'vepbt0';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…', $fetchedPost->getTitle());
        $this->assertEquals('shittyfoodporn', $fetchedPost->getSubreddit());
        $this->assertEquals('https://i.imgur.com/ThRMZx5.jpg', $fetchedPost->getUrl());
        $this->assertEquals('2022-06-17 20:29:22', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEmpty($fetchedPost->getAuthorText());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_LINK, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_IMAGE, $contentType->getName());
    }

    /**
     * https://www.reddit.com/r/German/comments/vlyukg/if_you_are_an_intermediate_level_learner_i/
     *
     * @return void
     */
    public function testSaveTextPost()
    {
        $redditId = 'vlyukg';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('If you are an intermediate level learner, I strongly suggest you give the book "Tintenherz" a try', $fetchedPost->getTitle());
        $this->assertEquals('German', $fetchedPost->getSubreddit());
        $this->assertEquals('https://www.reddit.com/r/German/comments/vlyukg/if_you_are_an_intermediate_level_learner_i/', $fetchedPost->getUrl());
        $this->assertEquals('2022-06-27 16:00:42', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals("I've been reading this book for the past weeks and I'm loving the pace in which I can read it. I feel like it's perfectly suited for B1/B2 level learners (I'd say even A2 learners could read it, albeit in a slower pace).

It is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it's not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.", $fetchedPost->getAuthorText());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_LINK, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_TEXT, $contentType->getName());
    }

    /**
     * https://www.reddit.com/r/golang/comments/v443nh/golang_tutorial_how_to_implement_concurrency_with/
     *
     * @return void
     */
    public function testParseVideoPost()
    {
        $redditId = 'v443nh';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('Golang Tutorial | How To Implement Concurrency With Goroutines and Channels', $fetchedPost->getTitle());
        $this->assertEquals('golang', $fetchedPost->getSubreddit());
        $this->assertEquals('https://youtu.be/bbgip1-ZbZg', $fetchedPost->getUrl());
        $this->assertEquals('2022-06-03 17:11:50', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEmpty($fetchedPost->getAuthorText());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_LINK, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_VIDEO, $contentType->getName());
    }

    /**
     * https://www.reddit.com/r/Tremors/comments/v27nr7/all_my_recreations_of_magazine_covers_from/
     *
     * @return void
     */
    public function testParseGalleryPost()
    {
        $redditId = 'v27nr7';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('All my recreations of magazine covers from Tremors 2 so far', $fetchedPost->getTitle());
        $this->assertEquals('Tremors', $fetchedPost->getSubreddit());
        $this->assertEquals('https://www.reddit.com/gallery/v27nr7', $fetchedPost->getUrl());
        $this->assertEquals('2022-06-01 03:31:38', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEmpty($fetchedPost->getAuthorText());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_LINK, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_IMAGE_GALLERY, $contentType->getName());
    }

    /**
     * https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/ia1smh6/?context=3 Use parent_id to get actual Post.
     *
     * @return void
     */
    public function testParseCommentPost()
    {
        $redditId = 'ia1smh6';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_COMMENT, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('Passed my telc B2 exam with a great score (275/300). Super stoked about it!', $fetchedPost->getTitle());
        $this->assertEquals('German', $fetchedPost->getSubreddit());
        $this->assertEquals('https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/', $fetchedPost->getUrl());
        $this->assertEquals('2022-05-26 10:42:40', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals('Congrats! What did your study routine look like leading up to it?', $fetchedPost->getAuthorText());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_COMMENT, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_TEXT, $contentType->getName());
    }

    public function testGetComments()
    {
        $redditId = 'vlyukg';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);
        $this->manager->savePost($post);
        $fetchedPost = $this->manager->getPostByRedditId($redditId);

        $comments = $this->manager->getCommentsFromApiByPost($fetchedPost);
        $this->assertCount(16, $comments);

        $this->assertInstanceOf(Comment::class, $comments[0]);

        // Test fetch Comment from DB
        $commentRedditId = 'idygho1';
        $comment = $this->manager->getCommentByRedditId($commentRedditId);
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($redditId, $comment->getParentPostId());
        $this->assertEquals('It\'s one of the few German books I\'ve read for which I would rate the language as "easy". Good for building confidence in reading.', $comment->getText());
        $this->assertEmpty($comment->getParentCommentId());

        // ie09fz0

        // Test fetch Comment replies from Comment
    }
}
