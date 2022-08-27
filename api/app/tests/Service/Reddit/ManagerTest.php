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

    /**
     * https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
     *
     * @return void
     */
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
     * https://www.reddit.com/r/coolguides/comments/won0ky/i_learned_how_to_whistle_from_this_in_less_than_5/
     *
     * @return void
     */
    public function testSaveRedditHostedImagePost()
    {
        $redditId = 'won0ky';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('I learned how to whistle from this in less than 5 minutes.', $fetchedPost->getTitle());
        $this->assertEquals('coolguides', $fetchedPost->getSubreddit());
        $this->assertEquals('https://i.redd.it/cnfk33iv9sh91.jpg', $fetchedPost->getUrl());
        $this->assertEquals('2022-08-15 01:52:53', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
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

        $this->assertEquals("&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;I&amp;#39;ve been reading this book for the past weeks and I&amp;#39;m loving the pace in which I can read it. I feel like it&amp;#39;s perfectly suited for B1/B2 level learners (I&amp;#39;d say even A2 learners could read it, albeit in a slower pace).&lt;/p&gt;\n\n&lt;p&gt;It is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it&amp;#39;s not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;", $fetchedPost->getAuthorTextRawHtml());

        $this->assertEquals("<div class=\"md\"><p>I've been reading this book for the past weeks and I'm loving the pace in which I can read it. I feel like it's perfectly suited for B1/B2 level learners (I'd say even A2 learners could read it, albeit in a slower pace).</p>

<p>It is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it's not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.</p>
</div>", $fetchedPost->getAuthorTextHtml());

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
        $this->assertEquals("&lt;div class=\"md\"&gt;&lt;p&gt;Congrats! What did your study routine look like leading up to it?&lt;/p&gt;
&lt;/div&gt;", $fetchedPost->getAuthorTextRawHtml());

        $this->assertEquals("<div class=\"md\"><p>Congrats! What did your study routine look like leading up to it?</p>
</div>", $fetchedPost->getAuthorTextHtml());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_COMMENT, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_TEXT, $contentType->getName());
    }

    /**
     * https://www.reddit.com/r/me_irl/comments/wgb8wj/me_irl/
     *
     * @return void
     */
    public function testSaveGifPost()
    {
        $redditId = 'wgb8wj';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('me_irl', $fetchedPost->getTitle());
        $this->assertEquals('me_irl', $fetchedPost->getSubreddit());
        $this->assertEquals('https://preview.redd.it/kanpjvgbarf91.gif?format=mp4&s=d3c0bb16145d61e9872bda355b742cfd3031fd69', $fetchedPost->getUrl());
        $this->assertEquals('2022-08-04 20:25:21', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEmpty($fetchedPost->getAuthorText());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_LINK, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_GIF, $contentType->getName());
    }

    /**
     * https://www.reddit.com/r/Tremors/comments/utsmkw/tremors_poster_for_gallery1988
     *
     * @return void
     */
    public function testSaveTextPostWithImage()
    {
        $redditId = 'utsmkw';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('Tremors poster for Gallery1988', $fetchedPost->getTitle());
        $this->assertEquals('Tremors', $fetchedPost->getSubreddit());
        $this->assertEquals('https://www.reddit.com/r/Tremors/comments/utsmkw/tremors_poster_for_gallery1988/', $fetchedPost->getUrl());
        $this->assertEquals('2022-05-20 11:27:43', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals("I did a poster for Gallery1988 in L.A.  \nI called the artwork \"The floor is lava\"\n\n  \nFor all of you who are interested in a print, here's the link:\n\n[https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230](https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230)\n\nhttps://preview.redd.it/gcj91awy8m091.jpg?width=900&amp;format=pjpg&amp;auto=webp&amp;s=7cab4910712115bb273171653cc754b9077c1455", $fetchedPost->getAuthorText());

        $this->assertEquals("&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;I did a poster for Gallery1988 in L.A.&lt;br/&gt;\nI called the artwork &amp;quot;The floor is lava&amp;quot;&lt;/p&gt;\n\n&lt;p&gt;For all of you who are interested in a print, here&amp;#39;s the link:&lt;/p&gt;\n\n&lt;p&gt;&lt;a href=\"https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230\"&gt;https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230&lt;/a&gt;&lt;/p&gt;\n\n&lt;p&gt;&lt;a href=\"https://preview.redd.it/gcj91awy8m091.jpg?width=900&amp;amp;format=pjpg&amp;amp;auto=webp&amp;amp;s=7cab4910712115bb273171653cc754b9077c1455\"&gt;https://preview.redd.it/gcj91awy8m091.jpg?width=900&amp;amp;format=pjpg&amp;amp;auto=webp&amp;amp;s=7cab4910712115bb273171653cc754b9077c1455&lt;/a&gt;&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;", $fetchedPost->getAuthorTextRawHtml());

        $this->assertEquals("<div class=\"md\"><p>I did a poster for Gallery1988 in L.A.<br/>\nI called the artwork \"The floor is lava\"</p>\n
<p>For all of you who are interested in a print, here's the link:</p>\n
<p><a href=\"https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230\">https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230</a></p>\n
<p><a href=\"https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&s=7cab4910712115bb273171653cc754b9077c1455\">https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&s=7cab4910712115bb273171653cc754b9077c1455</a></p>
</div>", $fetchedPost->getAuthorTextHtml());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_LINK, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_TEXT, $contentType->getName());
    }

    /**
     * https://www.reddit.com/r/Unexpected/comments/tl8qic/i_think_i_married_a_psychopath/
     *
     * @return void
     */
    public function testSaveRedditVideoPost()
    {
        $redditId = 'tl8qic';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('I think I married a psychopath', $fetchedPost->getTitle());
        $this->assertEquals('Unexpected', $fetchedPost->getSubreddit());
        $this->assertEquals('https://v.redd.it/8u3caw3zm6p81/DASH_720.mp4?source=fallback', $fetchedPost->getUrl());
        $this->assertEquals('2022-03-23 19:11:31', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEmpty($fetchedPost->getAuthorText());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_LINK, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_VIDEO, $contentType->getName());
    }

    public function testGetComments()
    {
        $redditId = 'vlyukg';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);
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
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);
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
}
