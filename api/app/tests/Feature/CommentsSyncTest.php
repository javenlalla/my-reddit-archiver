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

class CommentsSyncTest extends KernelTestCase
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

    public function testGetComments()
    {
        $redditId = 'vlyukg';
        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_LINK . '_' . $redditId);

        $comments = $this->manager->syncCommentsFromApiByPost($content->getPost());
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
        $this->assertEquals('It\'s one of the few German books I\'ve read for which I would rate the language as "easy". Good for building confidence in reading.', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
        $this->assertEmpty($comment->getParentComment());

        // Test fetch Comment replies from Comment.
        $commentRedditId = 'idy4nd0';
        $comment = $this->manager->getCommentByRedditId($commentRedditId);
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($redditId, $comment->getParentPost()->getRedditId());
        $this->assertEquals('Can you share me the front page of the book? Or download link if you have?', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
        $this->assertEmpty($comment->getParentComment());

        $replies = $comment->getReplies();
        $this->assertCount(2, $replies);
        $this->assertEquals("https://www.amazon.com/-/es/Cornelia-Funke/dp/3791504657\n\nI don’t remember where I got it from. I downloaded it in my kindle", $replies[0]->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

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
        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_LINK . '_' . $redditId);
        $comments = $this->manager->syncCommentsFromApiByPost($content->getPost());

        // Re-fetch Post.
        $fetchedPost = $this->manager->getPostByRedditId($redditId);

        // Verify top-level Comments count.
        $this->assertCount(408, $fetchedPost->getComments());

        // Verify all Comments and Replies count.
        $allCommentsCount = $this->manager->getAllCommentsCountFromPost($fetchedPost);
        $this->assertEquals(575, $allCommentsCount);

        // Basic Comment verification.
        $comment = $this->manager->getCommentByRedditId('icrhr47');
        $this->assertEquals('Mufbutt -- needs a little Imodium or less fiber.', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        // Verify top-level Comment with highest up-votes.
        $comment = $this->manager->getCommentByRedditId('icrxv93');
        $this->assertEquals('Look for berries that might be poisonous that are making the triceratops sick', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        //Verify a Reply found within "Continue this thread."
        $comment = $this->manager->getCommentByRedditId('icrovq6');
        $this->assertEquals('And things can be neither.', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        // Verify last or closest-to-last Comment on Post.
        $comment = $this->manager->getCommentByRedditId('icta0qr');
        $this->assertEquals('Does she go under the name “Amber” by any chance?', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        // Verify Comment found in "x more replies."
        $comment = $this->manager->getCommentByRedditId('icti9mw');
        $this->assertEquals('I got more!', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
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
        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_LINK . '_' . $redditId);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $comments = $this->manager->syncCommentsFromApiByPost($fetchedPost);
        $this->assertCount(876, $comments);
        $this->assertInstanceOf(Comment::class, $comments[0]);

        // Re-fetch Post.
        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $comments = $fetchedPost->getComments();
        $this->assertCount(876, $comments);
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
        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_LINK . '_' . $redditId);

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
        $redditId = 'wf1e8p';
        $commentRedditId = 'iirwrq4';
        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_COMMENT . '_' . $commentRedditId);

        $comments = $this->manager->syncCommentsFromApiByPost($content->getPost());
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
        $redditId = 'wf1e8p';
        $commentRedditId = 'iirwrq4';
        $content = $this->manager->syncContentFromApiByFullRedditId(Kind::KIND_COMMENT . '_' . $commentRedditId);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('Exercising almost daily for up to an hour at a low/mid intensity (50-70% heart rate, walking/jogging/cycling) helps reduce fat and lose weight (permanently), restores the body\'s fat balance and has other health benefits related to the body\'s fat and sugar', $fetchedPost->getTitle());
        $this->assertEquals('science', $fetchedPost->getSubreddit());
        $this->assertEquals('https://www.mdpi.com/2072-6643/14/8/1605/htm', $fetchedPost->getUrl());
        $this->assertEquals('2022-08-03 08:51:21', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));

        $comment = $content->getComment();
        $this->assertEquals("I've recently started running after not running for 10+ years. This was the single biggest piece of advice I got.\n\nGet a good heartrate monitor and don't go above 150. Just maintain 140-150. I was shocked at how much longer I could run for. I hadn't run since highschool and I ran a 5k cold turkey. It was a slow 5k but I ran the whole time. Pace is everything.", $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        $kind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $kind);
        $this->assertEquals(Kind::KIND_COMMENT, $kind->getRedditKindId());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::CONTENT_TYPE_EXTERNAL_LINK, $type->getName());
    }

    /**
     * Verify that when a Comment has edited or modified revisions, the latest
     * version of the text can be retrieved.
     *
     * @return void
     */
    public function testRetrieveLatestCommentAuthorTextVersion()
    {
        $comment = $this->commentRepository->findOneBy(['redditId' => 'xc0006']);
        $this->assertCount(1, $comment->getCommentAuthorTexts());

        $commentAuthorText = $comment->getCommentAuthorTexts()->get(0);
        $authorText = $commentAuthorText->getAuthorText();
        $this->assertEquals("It’s called Ultra Ego", $authorText->getText());

        // Manually out-date the current Comment Author Text.
        $commentAuthorText->setCreatedAt(DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2015-01-01 00:00:00'));
        $this->entityManager->persist($commentAuthorText);

        $updatedRevisions = [
            [
                'text' => 'Updated revision text 1.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2022-01-05 00:00:00'),
            ],
            [
                'text' => 'Updated revision text 2.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2022-06-05 00:00:00'),
            ],
            [
                'text' => 'Updated revision text 3.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-01-05 00:00:00'),
            ],
            [
                'text' => 'Updated revision text 4.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-04-05 00:00:00'),
            ],
        ];

        // Create new revisions.
        foreach ($updatedRevisions as $updatedRevision) {
            $updatedText = $updatedRevision['text'];
            $authorText = new AuthorText();
            $authorText->setText($updatedText);
            $authorText->setTextRawHtml($updatedText);
            $authorText->setTextHtml($updatedText);
            $commentAuthorText = new CommentAuthorText();
            $commentAuthorText->setAuthorText($authorText);
            $commentAuthorText->setCreatedAt($updatedRevision['createdAt']);

            $comment->addCommentAuthorText($commentAuthorText);
            $this->entityManager->persist($comment);
        }
        $this->entityManager->flush();

        // Re-fetch Comment.
        $comment = $this->commentRepository->findOneBy(['redditId' => 'xc0006']);
        $this->assertCount(5, $comment->getCommentAuthorTexts());

        // Verify the latest Author Text was retrieved.
        $currentCommentAuthorTextRevision = $comment->getLatestCommentAuthorText();
        $authorText = $currentCommentAuthorTextRevision->getAuthorText();
        $this->assertEquals($updatedRevisions[1]['text'], $authorText->getText());
    }
}
