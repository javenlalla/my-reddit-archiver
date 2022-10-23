<?php

namespace App\Tests\Feature;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\ContentType;
use App\Entity\Kind;
use App\Entity\Post;
use App\Repository\CommentRepository;
use App\Service\Reddit\Manager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JsonUrlSyncTest extends KernelTestCase
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
     * Verify a Saved Comment that is multiple levels deep within a Comment Tree
     * can be persisted along with the Comment's parents and replies.
     *
     * https://www.reddit.com/r/gaming/comments/xj8f7g/comment/ip7pedq/
     *
     * @return void
     */
    public function testSyncCommentPostMultipleLevelsDeepFromJsonUrl()
    {
        $postRedditId = 'xj8f7g';
        $commentRedditId = 'ip7pedq';
        $kind = Kind::KIND_COMMENT;
        $postLink = 'https://www.reddit.com/r/gaming/comments/xj8f7g/comment/ip7pedq/';

        $content = $this->manager->syncContentFromJsonUrl($kind, $postLink);

        $comment = $content->getComment();
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($commentRedditId, $comment->getRedditId());
        $this->assertEquals('Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably', $comment->getText());
        // @TODO: Enable this when createdAt is added to Comments. Date here is already the expected datetime for this Comment.
        // $this->assertEquals('2022-09-20 16:38:58', $comment->getCreatedAt());
        // @TODO: Enable when the following properties have been added to the Comment entity.
        // $this->assertEquals($authorText, $comment->getAuthorText());
        // $this->assertEquals($authorTextRawHtml, $comment->getAuthorTextRawHtml());
        // $this->assertEquals($authorTextHtml, $comment->getAuthorTextHtml());

        $post = $content->getPost();

        $this->assertNotEmpty($post->getId());
        $this->assertEquals($postRedditId, $post->getRedditId());
        $this->assertEquals('Star Citizen passes half billion dollars funding milestone, still no game launches in sight', $post->getTitle());
        $this->assertEquals('gaming', $post->getSubreddit());
        $this->assertEquals('https://i.redd.it/d0s8oagaj0p91.png', $post->getUrl());
        $this->assertEquals('https://reddit.com/r/gaming/comments/xj8f7g/star_citizen_passes_half_billion_dollars_funding/', $post->getRedditPostUrl());
        $this->assertEquals('2022-09-20 13:10:22', $post->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEmpty($post->getAuthorText());
        $this->assertEmpty($post->getAuthorTextRawHtml());
        $this->assertEmpty($post->getAuthorTextHtml());

        $kind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $kind);
        $this->assertEquals(Kind::KIND_COMMENT, $kind->getRedditKindId());

        $contentType = $content->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_IMAGE, $contentType->getName());

        // Verify all top Comments have been persisted.
        $this->assertEquals(24, $post->getComments()->count());

        // Verify persisted Saved Comment as matching the Saved Post record.
        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7pedq']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably', $comment->getText());
        $this->assertEquals('ip7o4ld', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7o4ld']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Yes. He\'s yelling in one and not the other.', $comment->getText());
        $this->assertEquals('ip7mwwz', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7mwwz']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Did they at least use a different picture of him?', $comment->getText());
        $this->assertEquals('ip7liqr', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7liqr']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Lol, not for fifa 21 and 22. Both are Mbappe', $comment->getText());
        $this->assertEquals('ip7goiv', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7goiv']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Hey, That\'s not true! They also change the person on the cover too. It\'s like tens of minutes of work.', $comment->getText());
        $this->assertEquals('ip75qvb', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip75qvb']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('To be fair changing the 21 to 22 on the cover of *insert sports game here* is still more substantial than the progress we\'re seeing on Star Citizen. Now if EA starts charging $10,000 for away game colors they\'ll be in the same ballpark.', $comment->getText());
        $this->assertEquals('ip73wew', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip73wew']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals("&gt;At the same time, EA execs might be \"huh, so we don't actually have to develop any game to get a product in the first place.\"\n\nHow's that different from what they're doing now?", $comment->getText());
        $this->assertEquals('ip72pep', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip72pep']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('At the same time, EA execs might be "huh, so we don\'t actually have to develop any game to get a product in the first place."', $comment->getText());
        $this->assertEquals('ip721ih', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip721ih']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('EA execs point at this and go "See!? This is what you get when there is no set timetable and no crunch!"', $comment->getText());
        $this->assertEquals('ip6va91', $comment->getParentComment()->getRedditId());

        // Top-level Comment.
        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip6va91']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('I have to say it is a very interesting case study of open game development and crowdfunding.', $comment->getText());
        $this->assertEmpty($comment->getParentComment());
        $this->assertEquals(0, $comment->getDepth());
    }

    /**
     * Verify a Saved Comment in a Comment Tree but has no replies.
     *
     * https://www.reddit.com/r/ProgrammerHumor/comments/xj50gl/microscopic/ip95ter/
     *
     * @return void
     */
    public function testSyncCommentPostMultipleLevelsDeepWithNoReplies()
    {
        $redditId = 'xj50gl';
        $commentRedditId = 'ip95ter';
        $kind = Kind::KIND_COMMENT;
        $postLink = '/r/ProgrammerHumor/comments/xj50gl/microscopic/ip95ter/';

        $content = $this->manager->syncContentFromJsonUrl($kind, $postLink);
        $this->assertInstanceOf(Content::class, $content);

        $post = $content->getPost();
        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals($redditId, $post->getRedditId());
        $this->assertEquals('microscopic', $post->getTitle());
        $this->assertEquals('ProgrammerHumor', $post->getSubreddit());
        $this->assertEquals('https://i.redd.it/4kp1p03jpzo91.png', $post->getUrl());
        $this->assertEquals('https://reddit.com/r/ProgrammerHumor/comments/xj50gl/microscopic/', $post->getRedditPostUrl());
        $this->assertEquals('2022-09-20 10:22:59', $post->getCreatedAt()->format('Y-m-d H:i:s'));

        $kind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $kind);
        $this->assertEquals(Kind::KIND_COMMENT, $kind->getRedditKindId());

        $contentType = $content->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_IMAGE, $contentType->getName());

        $comment = $content->getComment();
        $this->assertEquals($commentRedditId, $comment->getRedditId());
        // @TODO: Enable once createdAt is added to Comment Entity.
        // $this->assertEquals('2022-09-20 22:18:41', $comment->getCreatedAt());
    }

    /**
     * Verify if two different Comments from the same Post are Saved, no unique
     * constraint violations are thrown.
     *
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testMultpleSavedCommentsFromSamePost()
    {
        $this->markTestSkipped('The logic required to make this test passable requires more investigation. Marking `Skipped` for now.');

        $redditId = 'f83v7ro';
        $kind = Kind::KIND_COMMENT;
        $postLink = '/r/AskReddit/comments/dyu2uy/joke_lovers_of_reddit_whats_a_great_joke/f83v7ro/';
        $post = $this->manager->syncContentFromJsonUrl($kind, $postLink);

        $kind = Kind::KIND_COMMENT;
        $postLink = '/r/AskReddit/comments/dyu2uy/joke_lovers_of_reddit_whats_a_great_joke/f83nvbg/';
        $post = $this->manager->syncContentFromJsonUrl($kind, $postLink);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals($redditId, $post->getRedditId());
        $this->assertEquals('Joke lovers of Reddit, what’s a great joke?', $post->getTitle());
        $this->assertEquals('AskReddit', $post->getSubreddit());
        $this->assertEquals('https://www.reddit.com/r/AskReddit/comments/dyu2uy/joke_lovers_of_reddit_whats_a_great_joke/', $post->getUrl());
        $this->assertEquals('https://reddit.com/r/AskReddit/comments/dyu2uy/joke_lovers_of_reddit_whats_a_great_joke/', $post->getRedditPostUrl());
        $this->assertEquals('2019-11-20 03:51:00', $post->getCreatedAt()->format('Y-m-d H:i:s'));

        $postType = $post->getType();
        $this->assertInstanceOf(Kind::class, $postType);
        $this->assertEquals(Kind::KIND_COMMENT, $postType->getRedditTypeId());

        $postContentType = $post->getContentType();
        $this->assertInstanceOf(ContentType::class, $postContentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_TEXT, $postContentType->getName());
    }

    public function testBasicCommentContentJsonUrlSync()
    {
        $originalPostUrl =  'https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/ia1smh6/';
        $redditId =  'ia1smh6';
        $type =  Kind::KIND_COMMENT;
        $contentType =  ContentType::CONTENT_TYPE_TEXT;
        $title =  'Passed my telc B2 exam with a great score (275/300). Super stoked about it!';
        $subreddit =  'German';
        $url =  'https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/';
        $createdAt =  '2022-05-26 09:36:55';
        $authorText =  'Congrats! What did your study routine look like leading up to it?';
        $authorTextRawHtml =  "&lt;div class=\"md\"&gt;&lt;p&gt;Congrats! What did your study routine look like leading up to it?&lt;/p&gt;
&lt;/div&gt;";
        $authorTextHtml = "<div class=\"md\"><p>Congrats! What did your study routine look like leading up to it?</p>
</div>";

        $content = $this->manager->syncContentFromJsonUrl($type, $originalPostUrl);

        $comment = $content->getComment();
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($redditId, $comment->getRedditId());
        $this->assertEquals($authorText, $comment->getText());
        // @TODO: Enable this when createdAt is added to Comments. Date here is already the expected datetime for this Comment.
        // $this->assertEquals('2022-05-26 10:42:40', $comment->getCreatedAt());
        // @TODO: Enable when the following properties have been added to the Comment entity.
        // $this->assertEquals($authorText, $comment->getAuthorText());
        // $this->assertEquals($authorTextRawHtml, $comment->getAuthorTextRawHtml());
        // $this->assertEquals($authorTextHtml, $comment->getAuthorTextHtml());

        $post = $content->getPost();

        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals('uy3sx1', $post->getRedditId());
        $this->assertEquals($title, $post->getTitle());
        $this->assertEquals($subreddit, $post->getSubreddit());
        $this->assertEquals('https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/', $post->getUrl());
        $this->assertEquals($createdAt, $post->getCreatedAt()->format('Y-m-d H:i:s'));

        $contentKind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $contentKind);
        $this->assertEquals($type, $contentKind->getRedditKindId());

        $type = $content->getContentType();
        $this->assertInstanceOf(ContentType::class, $type);
        $this->assertEquals($contentType, $type->getName());

        $this->assertEquals("I’d be glad to offer any advice.", $post->getAuthorText());
        $this->assertEquals("&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;I’d be glad to offer any advice.&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;", $post->getAuthorTextRawHtml());
        $this->assertEquals("<div class=\"md\"><p>I’d be glad to offer any advice.</p>\n</div>", $post->getAuthorTextHtml());
    }
}
