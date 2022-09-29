<?php

namespace App\Tests\Feature;

use App\Entity\ContentType;
use App\Entity\Post;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Service\Reddit\Hydrator;
use App\Service\Reddit\Manager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JsonUrlSyncTest extends KernelTestCase
{
    private Manager $manager;

    private CommentRepository $commentRepository;

    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->commentRepository = $container->get(CommentRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * Similar test to `testSaveImagePost` except persistence is executed based
     * on the Post's original Reddit .json URL.
     *
     * All assertions MUST match `testSaveImagePost`.
     *
     * https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
     *
     * @return void
     */
    public function testSaveImagePostFromJsonUrl()
    {
        $redditId = 'vepbt0';
        $kind = Hydrator::TYPE_LINK;
        $postLink = 'https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/';

        $post = $this->manager->syncPostFromJsonUrl($kind, $postLink);

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
        $this->assertGreaterThan(50, $post->getComments()->count());
    }

    /**
     * Similar test to `testSaveImagePost` except persistence is executed based
     * on the Post's original Reddit .json URL.
     *
     * All assertions MUST match `testSyncCommentPost`.
     *
     * https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/ia1smh6/?context=3
     *
     * @return void
     */
    public function testSyncCommentPostFromJsonUrl()
    {
        $redditId = 'ia1smh6';
        $kind = Hydrator::TYPE_COMMENT;
        $postLink = 'https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/ia1smh6/';

        $post = $this->manager->syncPostFromJsonUrl($kind, $postLink);

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
     * Verify a Saved Comment that is multiple levels deep within a Comment Tree
     * can be persisted along with the Comment's parents and replies.
     *
     * https://www.reddit.com/r/gaming/comments/xj8f7g/comment/ip7pedq/
     *
     * @return void
     */
    public function testSyncCommentPostMultipleLevelsDeepFromJsonUrl()
    {
        $redditId = 'ip7pedq';
        $kind = Hydrator::TYPE_COMMENT;
        $postLink = 'https://www.reddit.com/r/gaming/comments/xj8f7g/comment/ip7pedq/';

        $post = $this->manager->syncPostFromJsonUrl($kind, $postLink);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('Star Citizen passes half billion dollars funding milestone, still no game launches in sight', $fetchedPost->getTitle());
        $this->assertEquals('gaming', $fetchedPost->getSubreddit());
        $this->assertEquals('https://i.redd.it/d0s8oagaj0p91.png', $fetchedPost->getUrl());
        $this->assertEquals('https://reddit.com/r/gaming/comments/xj8f7g/star_citizen_passes_half_billion_dollars_funding/', $fetchedPost->getRedditPostUrl());
        $this->assertEquals('2022-09-20 16:38:58', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals('Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably', $fetchedPost->getAuthorText());
        $this->assertEquals("&lt;div class=\"md\"&gt;&lt;p&gt;Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably&lt;/p&gt;
&lt;/div&gt;", $fetchedPost->getAuthorTextRawHtml());

        $this->assertEquals("<div class=\"md\"><p>Yeah, same photoshoot probably, they had to decide between the two pics bit decided that they would just use the extra next year. Ea marketing meeting probably</p>
</div>", $fetchedPost->getAuthorTextHtml());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_COMMENT, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_TEXT, $contentType->getName());

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
        $this->assertEquals("&gt;At the same time, EA execs might be \"huh, so we don't actually have to develop any game to get a product in the first place.\"\n
How's that different from what they're doing now?", $comment->getText());
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
}
