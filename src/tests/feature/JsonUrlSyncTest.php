<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Service\Reddit\Api\Context;
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
        $context = new Context('JsonUrlSyncTest:testSyncCommentPostMultipleLevelsDeepFromJsonUrl');

        $postRedditId = 'xj8f7g';
        $commentRedditId = 'ip7pedq';
        $kind = Kind::KIND_COMMENT;
        $postLink = 'https://www.reddit.com/r/gaming/comments/xj8f7g/comment/ip7pedq/';

        $content = $this->manager->syncContentFromJsonUrl($context, $kind, $postLink);

        $comment = $content->getComment();
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($commentRedditId, $comment->getRedditId());
        $this->assertEquals('Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
        $this->assertEquals('2022-09-20 16:38:58', $comment->getCommentAuthorTexts()->get(0)->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals('Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
        $this->assertEquals("&lt;div class=\"md\"&gt;&lt;p&gt;Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably&lt;/p&gt;\n&lt;/div&gt;", $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getTextRawHtml());
        $this->assertEquals("<div class=\"md\"><p>Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably</p>\n</div>", $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getTextHtml());

        $post = $content->getPost();

        $this->assertNotEmpty($post->getId());
        $this->assertEquals($postRedditId, $post->getRedditId());
        $this->assertEquals('Star Citizen passes half billion dollars funding milestone, still no game launches in sight', $post->getTitle());
        $this->assertEquals('gaming', $post->getSubreddit()->getName());
        $this->assertEquals('https://i.redd.it/d0s8oagaj0p91.png', $post->getUrl());
        $this->assertEquals('https://www.reddit.com/r/gaming/comments/xj8f7g/star_citizen_passes_half_billion_dollars_funding/', $post->getRedditPostUrl());
        $this->assertEquals('2022-09-20 13:10:22', $post->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEmpty($post->getPostAuthorTexts());
        $this->assertEmpty($post->getPostAuthorTexts());
        $this->assertEmpty($post->getPostAuthorTexts());

        $kind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $kind);
        $this->assertEquals(Kind::KIND_COMMENT, $kind->getRedditKindId());

        $type = $post->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::CONTENT_TYPE_IMAGE, $type->getName());

        // Verify all top Comments have been persisted.
        $this->assertEquals(23, $post->getComments()->count());

        // Verify persisted Saved Comment as matching the Saved Post record.
        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7pedq']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably',
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
        $this->assertEquals('ip7o4ld', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7o4ld']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Yes. He\'s yelling in one and not the other.',
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
        $this->assertEquals('ip7mwwz', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7mwwz']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Did they at least use a different picture of him?',
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
        $this->assertEquals('ip7liqr', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7liqr']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Lol, not for fifa 21 and 22. Both are Mbappe',
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
        $this->assertEquals('ip7goiv', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip7goiv']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('Hey, That\'s not true! They also change the person on the cover too. It\'s like tens of minutes of work.',
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
        $this->assertEquals('ip75qvb', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip75qvb']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('To be fair changing the 21 to 22 on the cover of *insert sports game here* is still more substantial than the progress we\'re seeing on Star Citizen. Now if EA starts charging $10,000 for away game colors they\'ll be in the same ballpark.',
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
        $this->assertEquals('ip73wew', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip73wew']);
        $this->assertCount(2, $comment->getReplies());
        $this->assertEquals("&gt;At the same time, EA execs might be \"huh, so we don't actually have to develop any game to get a product in the first place.\"\n\nHow's that different from what they're doing now?",
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
        $this->assertEquals('ip72pep', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip72pep']);
        $this->assertCount(1, $comment->getReplies());
        $this->assertEquals('At the same time, EA execs might be "huh, so we don\'t actually have to develop any game to get a product in the first place."',
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
        $this->assertEquals('ip721ih', $comment->getParentComment()->getRedditId());

        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip721ih']);
        $this->assertCount(3, $comment->getReplies());
        $this->assertEquals('EA execs point at this and go "See!? This is what you get when there is no set timetable and no crunch!"',
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
        $this->assertEquals('ip6va91', $comment->getParentComment()->getRedditId());

        // Top-level Comment.
        $comment = $this->commentRepository->findOneBy(['redditId' => 'ip6va91']);
        $this->assertCount(2, $comment->getReplies());
        $this->assertEquals('I have to say it is a very interesting case study of open game development and crowdfunding.',
            $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText()
        );
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
        $context = new Context('JsonUrlSyncTest:testSyncCommentPostMultipleLevelsDeepWithNoReplies');

        $redditId = 'xj50gl';
        $commentRedditId = 'ip95ter';
        $kind = Kind::KIND_COMMENT;
        $postLink = '/r/ProgrammerHumor/comments/xj50gl/microscopic/ip95ter/';

        $content = $this->manager->syncContentFromJsonUrl($context, $kind, $postLink);
        $this->assertInstanceOf(Content::class, $content);

        $post = $content->getPost();
        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals($redditId, $post->getRedditId());
        $this->assertEquals('microscopic', $post->getTitle());
        $this->assertEquals('ProgrammerHumor', $post->getSubreddit()->getName());
        $this->assertEquals('https://i.redd.it/4kp1p03jpzo91.png', $post->getUrl());
        $this->assertEquals('https://www.reddit.com/r/ProgrammerHumor/comments/xj50gl/microscopic/', $post->getRedditPostUrl());
        $this->assertEquals('2022-09-20 10:22:59', $post->getCreatedAt()->format('Y-m-d H:i:s'));

        $kind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $kind);
        $this->assertEquals(Kind::KIND_COMMENT, $kind->getRedditKindId());

        $type = $post->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::CONTENT_TYPE_IMAGE, $type->getName());

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
     */
    public function testMultipleSavedCommentsFromSamePost()
    {
        $context = new Context('JsonUrlSyncTest:testMultipleSavedCommentsFromSamePost');

        $redditId = 'dyu2uy';
        $kind = Kind::KIND_COMMENT;
        $contentLink = '/r/AskReddit/comments/dyu2uy/joke_lovers_of_reddit_whats_a_great_joke/f83v7ro/';
        $content = $this->manager->syncContentFromJsonUrl($context, $kind, $contentLink);

        $kind = Kind::KIND_COMMENT;
        $contentLink = '/r/AskReddit/comments/dyu2uy/joke_lovers_of_reddit_whats_a_great_joke/f83nvbg/';
        $content = $this->manager->syncContentFromJsonUrl($context, $kind, $contentLink);

        $firstComment = $this->commentRepository->findOneBy(['redditId' => 'f83v7ro']);
        $secondComment = $this->commentRepository->findOneBy(['redditId' => 'f83nvbg']);

        $this->assertNotEquals($firstComment->getContent()->getId(), $secondComment->getContent()->getId(), 'Separate Comments within the same Post should have different Content parents.');
        $this->assertEquals($firstComment->getParentPost()->getId(), $secondComment->getParentPost()->getId(), 'Separate Comments within the same Post should share the same Post record.');

        $post = $firstComment->getParentPost();
        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals($redditId, $post->getRedditId());
        $this->assertEquals('Joke lovers of Reddit, what’s a great joke?', $post->getTitle());
        $this->assertEquals('AskReddit', $post->getSubreddit()->getName());
        $this->assertEquals('https://www.reddit.com/r/AskReddit/comments/dyu2uy/joke_lovers_of_reddit_whats_a_great_joke/', $post->getUrl());
        $this->assertEquals('https://www.reddit.com/r/AskReddit/comments/dyu2uy/joke_lovers_of_reddit_whats_a_great_joke/', $post->getRedditPostUrl());
        $this->assertEquals('2019-11-20 00:57:10', $post->getCreatedAt()->format('Y-m-d H:i:s'));

        $kind = $firstComment->getContent()->getKind();
        $this->assertInstanceOf(Kind::class, $kind);
        $this->assertEquals(Kind::KIND_COMMENT, $kind->getRedditKindId());

        $type = $firstComment->getParentPost()->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::CONTENT_TYPE_TEXT, $type->getName());
    }

    /**
     * Verify a basic sync via the logic of syncing a Post's JSON URL.
     *
     * @return void
     */
    public function testBasicCommentContentJsonUrlSync()
    {
        $context = new Context('JsonUrlSyncTest:testBasicCommentContentJsonUrlSync');

        $originalPostUrl =  'https://www.reddit.com/r/TheSilphRoad/comments/10zrjou/my_new_stunlock_smeargle/j84z4vm/';
        $redditId =  'j84z4vm';
        $type =  Kind::KIND_COMMENT;
        $postType =  Type::CONTENT_TYPE_VIDEO;
        $title =  'My new Stun-Lock Smeargle!';
        $subreddit =  'TheSilphRoad';
        $url =  'https://v.redd.it/xkmtttug6lha1';
        $createdAt =  '2023-02-11 16:30:26';
        $authorText =  "You can take out the leaders and Giovanni with it... Take a picture of Shadow Registeel or Porygon. As long as you have a 35 or 40 energy charge move, this will work.    \n\nI didn't do the leaders because I still have 3 more pieces to get before I can do one.";
        $authorTextRawHtml =  "&lt;div class=\"md\"&gt;&lt;p&gt;You can take out the leaders and Giovanni with it... Take a picture of Shadow Registeel or Porygon. As long as you have a 35 or 40 energy charge move, this will work.    &lt;/p&gt;\n\n&lt;p&gt;I didn&amp;#39;t do the leaders because I still have 3 more pieces to get before I can do one.&lt;/p&gt;\n&lt;/div&gt;";
        $authorTextHtml = "<div class=\"md\"><p>You can take out the leaders and Giovanni with it... Take a picture of Shadow Registeel or Porygon. As long as you have a 35 or 40 energy charge move, this will work.    </p>\n\n<p>I didn't do the leaders because I still have 3 more pieces to get before I can do one.</p>\n</div>";

        $content = $this->manager->syncContentFromJsonUrl($context, $type, $originalPostUrl);

        $comment = $content->getComment();
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($redditId, $comment->getRedditId());
        $this->assertEquals($authorText, $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
        $this->assertEquals('2023-02-11 17:47:04', $comment->getCommentAuthorTexts()->get(0)->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals($authorText, $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
        $this->assertEquals($authorTextRawHtml, $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getTextRawHtml());
        $this->assertEquals($authorTextHtml, $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getTextHtml());

        $post = $content->getPost();

        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals('10zrjou', $post->getRedditId());
        $this->assertEquals($title, $post->getTitle());
        $this->assertEquals($subreddit, $post->getSubreddit()->getName());
        $this->assertEquals($url, $post->getUrl());
        $this->assertEquals($createdAt, $post->getCreatedAt()->format('Y-m-d H:i:s'));

        $contentKind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $contentKind);
        $this->assertEquals($type, $contentKind->getRedditKindId());

        $type = $post->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals($postType, $type->getName());
    }

    /**
     * A Post which has been cross-posted and thus has a Crosspost parent fails
     * to sync if the Crosspost parent has an Image Gallery because that data
     * is contained only within the Crosspost data, not within the target Post.
     * As a result, the current logic fails to detect a `Type` for the Post.
     *
     * Fix by pulling in the necessary Image Gallery data from the Crosspost
     * into the target Post.
     *
     * Verify the Post can then be synced successfully.
     *
     * @return void
     */
    public function testParentCrosspostHasImageGallery()
    {
        $context = new Context('JsonUrlSyncTest:testParentCrosspostHasImageGallery');

        $content = $this->manager->syncContentFromApiByFullRedditId($context, 't3_jjpv7n');

        $post = $content->getPost();
        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals('jjpv7n', $post->getRedditId());
        $this->assertEquals('Campaign/Awareness', $post->getFlairText());
        $this->assertCount(0, $post->getComments());

        // Verify the 8 Image Gallery assets from the Crosspost parent were
        // pulled in successfully.
        $this->assertCount(8, $post->getMediaAssets());
    }
}
