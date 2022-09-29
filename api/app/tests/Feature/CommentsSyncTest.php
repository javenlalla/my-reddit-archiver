<?php

namespace App\Tests\Feature;

use App\Entity\Comment;
use App\Entity\ContentType;
use App\Entity\Post;
use App\Entity\Type;
use App\Service\Reddit\Manager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CommentsSyncTest extends KernelTestCase
{
    private Manager $manager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
    }

    public function testGetComments()
    {
        $redditId = 'vlyukg';
        $post = $this->manager->getPostFromApiByRedditId(Type::TYPE_LINK, $redditId);
        $this->manager->savePost($post);
        $fetchedPost = $this->manager->getPostByRedditId($redditId);

        $comments = $this->manager->syncCommentsFromApiByPost($fetchedPost);
        $this->assertCount(16, $comments);
        $this->assertInstanceOf(Comment::class, $comments[0]);

        // Re-fetch Post.
        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $comments = $fetchedPost->getComments();
        $this->assertCount(16, $comments);

        // Test basic fetch Comment from DB.
        $commentRedditId = 'idygho1';
        $comment = $this->manager->getCommentByRedditId($commentRedditId);
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($redditId, $comment->getParentPost()->getRedditId());
        $this->assertEquals('It\'s one of the few German books I\'ve read for which I would rate the language as "easy". Good for building confidence in reading.', $comment->getText());
        $this->assertEmpty($comment->getParentComment());

        // Test fetch Comment replies from Comment.
        $commentRedditId = 'idy4nd0';
        $comment = $this->manager->getCommentByRedditId($commentRedditId);
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($redditId, $comment->getParentPost()->getRedditId());
        $this->assertEquals('Can you share me the front page of the book? Or download link if you have?', $comment->getText());
        $this->assertEmpty($comment->getParentComment());

        $replies = $comment->getReplies();
        $this->assertCount(2, $replies);
        $this->assertEquals("https://www.amazon.com/-/es/Cornelia-Funke/dp/3791504657

I don’t remember where I got it from. I downloaded it in my kindle", $replies[0]->getText());

        // Test fetch a Comment reply at least two levels deep and verify its Parent Comment chain.
        $commentRedditId = 'iebbk73';
        $comment = $this->manager->getCommentByRedditId($commentRedditId);
        $parentComment = $comment->getParentComment();
        $this->assertEquals('ieare0z', $parentComment->getRedditId());

        $parentComment = $parentComment->getParentComment();
        $this->assertEquals('ie09fz0', $parentComment->getRedditId());
    }

    /**
     * Verify fetching and hydrating a large Comment set from the API persists
     * successfully to the database.
     *
     * Post: https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
     *
     * @return void
     */
    public function testGetCommentsLargeCount()
    {
        $redditId = 'vepbt0';
        $post = $this->manager->getPostFromApiByRedditId(Type::TYPE_LINK, $redditId);
        $this->manager->savePost($post);
        $fetchedPost = $this->manager->getPostByRedditId($redditId);

        $comments = $this->manager->syncCommentsFromApiByPost($fetchedPost);
        // Re-fetch Post.
        $fetchedPost = $this->manager->getPostByRedditId($redditId);

        // Verify top-level Comments count.
        $this->assertCount(409, $fetchedPost->getComments());

        // Verify all Comments and Replies count.
        $allCommentsCount = $this->manager->getAllCommentsCountFromPost($fetchedPost);
        $this->assertEquals(576, $allCommentsCount);

        // Basic Comment verification.
        $comment = $this->manager->getCommentByRedditId('icrhr47');
        $this->assertEquals('Mufbutt -- needs a little Imodium or less fiber.', $comment->getText());

        // Verify top-level Comment with highest up-votes.
        $comment = $this->manager->getCommentByRedditId('icrxv93');
        $this->assertEquals('Look for berries that might be poisonous that are making the triceratops sick', $comment->getText());

        //Verify a Reply found within "Continue this thread."
        $comment = $this->manager->getCommentByRedditId('icrovq6');
        $this->assertEquals('And things can be neither.', $comment->getText());

        // Verify last or closest-to-last Comment on Post.
        $comment = $this->manager->getCommentByRedditId('icta0qr');
        $this->assertEquals('Does she go under the name “Amber” by any chance?', $comment->getText());

        // Verify Comment found in "x more replies."
        $comment = $this->manager->getCommentByRedditId('icti9mw');
        $this->assertEquals('I got more!', $comment->getText());
    }

    /**
     * Validate a test case in which a Post's Comment tree yielded a "more" object
     * with no children.
     *
     * Verify no errors are thrown when processing such a use case.
     *
     * https://www.reddit.com/r/coolguides/comments/won0ky/i_learned_how_to_whistle_from_this_in_less_than_5/
     *
     * @return void
     */
    public function testGetCommentsEmptyMore()
    {
        $redditId = 'won0ky';
        $post = $this->manager->getPostFromApiByRedditId(Type::TYPE_LINK, $redditId);
        $this->manager->savePost($post);
        $fetchedPost = $this->manager->getPostByRedditId($redditId);

        $comments = $this->manager->syncCommentsFromApiByPost($fetchedPost);
        $this->assertCount(878, $comments);
        $this->assertInstanceOf(Comment::class, $comments[0]);

        // Re-fetch Post.
        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $comments = $fetchedPost->getComments();
        $this->assertCount(878, $comments);
    }

    /**
     * Similar validation to the `testGetCommentsEmptyMore()` test except the
     * location in which the empty "more" object occurs happens earlier up in
     * the Comment tree for this Post.
     *
     * https://www.reddit.com/r/ProgrammerHumor/comments/wfylnl/when_you_use_a_new_library_without_reading_the/
     *
     * @return void
     */
    public function testGetCommentsInitialEmptyMore()
    {
        $redditId = 'wfylnl';
        $post = $this->manager->getPostFromApiByRedditId(Type::TYPE_LINK, $redditId);
        $this->manager->savePost($post);
        $fetchedPost = $this->manager->getPostByRedditId($redditId);

        $comments = $this->manager->syncCommentsFromApiByPost($fetchedPost);
        $this->assertCount(45, $comments);
        $this->assertInstanceOf(Comment::class, $comments[0]);

        // Re-fetch Post.
        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $comments = $fetchedPost->getComments();
        $this->assertCount(45, $comments);
    }

    /**
     * Validate a Post which links to an external site.
     *
     * https://www.reddit.com/r/science/comments/wf1e8p/exercising_almost_daily_for_up_to_an_hour_at_a/
     *
     * @return void
     */
    public function testSyncCommentsFromCommentPostMultipleLevelsDeep()
    {
        $redditId = 'iirwrq4';
        $post = $this->manager->getPostFromApiByRedditId(Type::TYPE_COMMENT, $redditId);
        $this->manager->savePost($post);
        $fetchedPost = $this->manager->getPostByRedditId($redditId);

        $comments = $this->manager->syncCommentsFromApiByPost($fetchedPost);
        $this->assertCount(524, $comments);
        $this->assertInstanceOf(Comment::class, $comments[0]);

        // Re-fetch Post.
        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $comments = $fetchedPost->getComments();
        $this->assertCount(524, $comments);
    }

    /**
     * Validate a Saved Comment Post in which the Comment is multiple levels deep
     * within the Comment tree.
     *
     * https://reddit.com/r/science/comments/wf1e8p/exercising_almost_daily_for_up_to_an_hour_at_a/iirwrq4/
     *
     * @return void
     */
    public function testSaveCommentPostMultipleLevelsDeep()
    {
        $redditId = 'iirwrq4';
        $post = $this->manager->getPostFromApiByRedditId(Type::TYPE_COMMENT, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('Exercising almost daily for up to an hour at a low/mid intensity (50-70% heart rate, walking/jogging/cycling) helps reduce fat and lose weight (permanently), restores the body\'s fat balance and has other health benefits related to the body\'s fat and sugar', $fetchedPost->getTitle());
        $this->assertEquals('science', $fetchedPost->getSubreddit());
        $this->assertEquals('https://www.mdpi.com/2072-6643/14/8/1605/htm', $fetchedPost->getUrl());
        $this->assertEquals('2022-08-03 12:43:19', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals('I\'ve recently started running after not running for 10+ years. This was the single biggest piece of advice I got.

Get a good heartrate monitor and don\'t go above 150. Just maintain 140-150. I was shocked at how much longer I could run for. I hadn\'t run since highschool and I ran a 5k cold turkey. It was a slow 5k but I ran the whole time. Pace is everything.', $fetchedPost->getAuthorText());
        $this->assertEquals("&lt;div class=\"md\"&gt;&lt;p&gt;I&amp;#39;ve recently started running after not running for 10+ years. This was the single biggest piece of advice I got.&lt;/p&gt;\n
&lt;p&gt;Get a good heartrate monitor and don&amp;#39;t go above 150. Just maintain 140-150. I was shocked at how much longer I could run for. I hadn&amp;#39;t run since highschool and I ran a 5k cold turkey. It was a slow 5k but I ran the whole time. Pace is everything.&lt;/p&gt;\n&lt;/div&gt;", $fetchedPost->getAuthorTextRawHtml());

        $this->assertEquals("<div class=\"md\"><p>I've recently started running after not running for 10+ years. This was the single biggest piece of advice I got.</p>\n
<p>Get a good heartrate monitor and don't go above 150. Just maintain 140-150. I was shocked at how much longer I could run for. I hadn't run since highschool and I ran a 5k cold turkey. It was a slow 5k but I ran the whole time. Pace is everything.</p>\n</div>", $fetchedPost->getAuthorTextHtml());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_COMMENT, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_TEXT, $contentType->getName());
    }
}
