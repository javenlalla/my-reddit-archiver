<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Entity\Comment;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Repository\MoreCommentRepository;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Items;
use App\Service\Reddit\Manager;
use App\Service\Reddit\Manager\Comments;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CommentsSyncTest extends KernelTestCase
{
    private Manager $manager;

    private Comments $commentsManager;

    private CommentRepository $commentRepository;

    private MoreCommentRepository $moreCommentRepository;

    private Items $itemsService;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->commentsManager = $container->get(Comments::class);
        $this->commentRepository = $container->get(CommentRepository::class);
        $this->moreCommentRepository = $container->get(MoreCommentRepository::class);
        $this->itemsService = $container->get(Items::class);
    }


    /**
     * Verify a syncing a Comment by its Reddit ID and confirming its Item Json
     * is also persisted.
     *
     * @return void
     */
    public function testSyncComment(): void
    {
        $context = new Context('CommentsSyncTest:testGetComments');
        $commentRedditId = 't1_ip914eh';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, $commentRedditId);

        $comment = $content->getComment();
        $this->assertEquals(str_replace('t1_', '', $commentRedditId), $comment->getRedditId());

        $commentItemJson = $this->itemsService->getItemInfoByRedditId($context, $commentRedditId);
        $this->assertEquals($commentRedditId, $commentItemJson->getRedditId());
    }

    /**
     * Verify a Comment's parent can be synced and persisted correctly.
     *
     * @return void
     */
    public function testSyncCommentParent(): void
    {
        $context = new Context('CommentsSyncTest:testGetComments');
        $commentRedditId = 't1_ip914eh';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, $commentRedditId);

        $comment = $content->getComment();

        $this->assertEquals('t1_ip90mlq', $comment->getParentCommentRedditId());
        $this->assertEmpty($comment->getParentComment());

        $parentComment = $this->commentsManager->syncParentComment($context, $comment);
        $this->assertInstanceOf(Comment::class, $parentComment);
        $this->assertInstanceOf(Comment::class, $comment->getParentComment());

        $this->assertEquals('t1_' . $parentComment->getRedditId(), $comment->getParentCommentRedditId());
        $this->assertCount(1, $parentComment->getReplies());
    }

    public function testSyncCommentChildren(): void
    {
        $this->markTestSkipped('Test to come.');
    }

    public function testSyncCommentMoreComments(): void
    {
        $this->markTestSkipped('Test to come.');
    }

    public function testSyncContentComments(): void
    {
        $this->markTestSkipped('Test to come.');
    }

    public function testGetComments()
    {
        $context = new Context('CommentsSyncTest:testGetComments');
        $redditId = 'uk7ctt';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_LINK . '_' . $redditId);

        $comments = $this->commentsManager->syncCommentsByContent($context, $content);
        $this->assertGreaterThan(30, count($comments));
        $this->assertInstanceOf(Comment::class, $comments[0]);

        // Test basic fetch Comment from DB.
        $commentRedditId = 'i7nss2g';
        $comment = $this->commentRepository->findOneBy(['redditId' => $commentRedditId]);
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($redditId, $comment->getParentPost()->getRedditId());
        $this->assertEquals('If anything Rhonda has the opposite problem - people keep demanding answers out of her because she\'s a scientist. I love it when she finally calls  Valentine out on it when they\'re on the roofs.', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
        $this->assertEmpty($comment->getParentComment());

        // Test fetch Comment replies from Comment.
        $commentRedditId = 'i7nm3bd';
        $comment = $this->commentRepository->findOneBy(['redditId' => $commentRedditId]);
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($redditId, $comment->getParentPost()->getRedditId());
        $this->assertEquals('Tremors is often touted as "the perfect movie" , as in not one scene is unnecessary.', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
        $this->assertEmpty($comment->getParentComment());

        $replies = $comment->getReplies();
        $this->assertCount(6, $replies);
        $this->assertEquals('Plus, as OP pointed out, the dynamics and characters are really great (especially when you compare them to films of the time, and now).', $replies[0]->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        // Test fetch a Comment reply at least two levels deep and verify its Parent Comment chain.
        $commentRedditId = 'i7oti6l';
        $comment = $this->commentRepository->findOneBy(['redditId' => $commentRedditId]);

        $parentComment = $comment->getParentComment();
        $this->assertEquals('i7okaqm', $parentComment->getRedditId());

        $parentComment = $parentComment->getParentComment();
        $this->assertEquals('i7oeht7', $parentComment->getRedditId());
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
        $context = new Context('CommentsSyncTest:testGetCommentsLargeCount');
        $redditId = 'vepbt0';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_LINK . '_' . $redditId);
        $comments = $this->commentsManager->syncAllCommentsByContent($context, $content);

        // Verify top-level Comments count.
        $post = $content->getPost();
        $this->assertGreaterThan(375, $post->getComments()->count());

        // Verify all Comments and Replies count.
        $allComments = $this->commentRepository->findBy(['parentPost' => $post]);
        $this->assertGreaterThan(500, count($allComments));

        // Basic Comment verification.
        $comment = $this->commentRepository->findOneBy(['redditId' => 'icrhr47']);

        $this->assertEquals('Mufbutt -- needs a little Imodium or less fiber.', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        // Verify top-level Comment with highest up-votes.
        $comment = $this->commentRepository->findOneBy(['redditId' => 'icrxv93']);

        $this->assertEquals('Look for berries that might be poisonous that are making the triceratops sick', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        //Verify a Reply found within "Continue this thread."
        $comment = $this->commentRepository->findOneBy(['redditId' => 'icrovq6']);

        $this->assertEquals('And things can be neither.', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        // Verify last or closest-to-last Comment on Post.
        $comment = $this->commentRepository->findOneBy(['redditId' => 'icta0qr']);

        $this->assertEquals('Does she go under the name “Amber” by any chance?', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        // Verify Comment found in "x more replies."
        // @TODO: Disabling this verification for now as syncing `More` Comments has been removed from fetching by default. Maybe look into adding a flag to include `More` when syncing "all" Comments and re-enable this verification.
        // $comment = $this->commentRepository->findOneBy(['redditId' => 'icti9mw']);
        // $this->assertEquals('I got more!', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());
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
        $context = new Context('CommentsSyncTest:testGetCommentsEmptyMore');
        $redditId = 'won0ky';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_LINK . '_' . $redditId);

        $comments = $this->commentsManager->syncAllCommentsByContent($context, $content);

        $this->assertGreaterThan(850, count($comments));
        $this->assertInstanceOf(Comment::class, $comments[0]);

        // Re-fetch Post.
        $comments = $content->getPost()->getComments();
        $this->assertGreaterThan(850, count($comments));
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
        $context = new Context('CommentsSyncTest:testGetCommentsInitialEmptyMore');
        $redditId = 'wfylnl';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_LINK . '_' . $redditId);

        $comments = $this->commentsManager->syncCommentsByContent($context, $content);
        $this->assertCount(45, $comments);
        $this->assertInstanceOf(Comment::class, $comments[0]);
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
        $context = new Context('CommentsSyncTest:testSyncCommentsFromCommentPostMultipleLevelsDeep');
        $redditId = 'wf1e8p';
        $commentRedditId = 'iirwrq4';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_COMMENT . '_' . $commentRedditId);

        $comments = $this->commentsManager->syncAllCommentsByContent($context, $content);
        $this->assertGreaterThan(500, $comments->count());
        $this->assertInstanceOf(Comment::class, $comments[0]);
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
        $context = new Context('CommentsSyncTest:testSaveCommentPostMultipleLevelsDeep');
        $redditId = 'wf1e8p';
        $commentRedditId = 'iirwrq4';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_COMMENT . '_' . $commentRedditId);

        $post = $content->getPost();
        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals($redditId, $post->getRedditId());
        $this->assertEquals('Exercising almost daily for up to an hour at a low/mid intensity (50-70% heart rate, walking/jogging/cycling) helps reduce fat and lose weight (permanently), restores the body\'s fat balance and has other health benefits related to the body\'s fat and sugar', $post->getTitle());
        $this->assertEquals('science', $post->getSubreddit()->getName());
        $this->assertEquals('https://www.mdpi.com/2072-6643/14/8/1605/htm', $post->getUrl());
        $this->assertEquals('2022-08-03 08:51:21', $post->getCreatedAt()->format('Y-m-d H:i:s'));

        $comment = $content->getComment();
        $this->assertEquals("I've recently started running after not running for 10+ years. This was the single biggest piece of advice I got.\n\nGet a good heartrate monitor and don't go above 150. Just maintain 140-150. I was shocked at how much longer I could run for. I hadn't run since highschool and I ran a 5k cold turkey. It was a slow 5k but I ran the whole time. Pace is everything.", $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        $kind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $kind);
        $this->assertEquals(Kind::KIND_COMMENT, $kind->getRedditKindId());

        $type = $post->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::CONTENT_TYPE_EXTERNAL_LINK, $type->getName());
    }

    /**
     * Verify syncing a Comment Tree in which the target Comment's Parent was
     * deleted on Reddit's side.
     *
     * Verify that despite the deletion, the entire Tree can still be synced.
     *
     * https://www.reddit.com/r/coolguides/comments/won0ky/comment/ikcjn2n/?utm_source=share&utm_medium=web2x&context=3
     *
     * @return void
     */
    public function testParentCommentWasDeleted()
    {
        $context = new Context('CommentsSyncTest:testParentCommentWasDeleted');
        $commentRedditId = 'ikcjn2n';
        $kind = Kind::KIND_COMMENT;
        $commentLink = 'https://www.reddit.com/r/coolguides/comments/won0ky/comment/ikcjn2n';

        $content = $this->manager->syncContentFromJsonUrl($context, $kind, $commentLink);

        $comment = $content->getComment();
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($commentRedditId, $comment->getRedditId());
        $this->assertEquals('I\'m tongue tied too, but all I got was slobber. Let us know if you manage. I\'ll practice and share too.', $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText());

        // https://www.reddit.com/r/coolguides/comments/won0ky/comment/ikcgnfz/.json
        $deletedParentComment = $comment->getParentComment();
        $this->assertEquals('ikcgnfz', $deletedParentComment->getRedditId());
        $this->assertEquals('[deleted]', $deletedParentComment->getAuthor());
        $this->assertEquals('[deleted]', $deletedParentComment->getLatestCommentAuthorText()->getAuthorText()->getText());

        // Assert Comment Tree continues above deleted Comment.
        // https://www.reddit.com/r/coolguides/comments/won0ky/comment/ikcd40i/.json
        $comment = $deletedParentComment->getParentComment();
        $this->assertEquals('ikcd40i', $comment->getRedditId());
        $this->assertEquals("Right? I don't understand the whole, push your tongue back in on itself step? Why are my fingers underneath my tongue? I'm just a slobbering mess...\n\nI can whistle normally. I can play the flute which is like advanced whitelisting, kind of? But this seems foreign to me", $comment->getLatestCommentAuthorText()->getAuthorText()->getText());

        // https://www.reddit.com/r/coolguides/comments/won0ky/comment/ikc4kki/.json
        $comment = $comment->getParentComment();
        $this->assertEquals('ikc4kki', $comment->getRedditId());
        $this->assertEquals("Out of breath, and mouth is numb, didn’t work", $comment->getLatestCommentAuthorText()->getAuthorText()->getText());
    }

    /**
     * Verify a deleted Top Level Comment is pulled in when syncing its Post.
     *
     * https://www.reddit.com/r/mildlyinfuriating/comments/a6ezwg/your_comment_was_removed_for_being_a_very_short/
     * Deleted Top Level Comment: https://www.reddit.com/r/mildlyinfuriating/comments/a6ezwg/comment/ebu7bsm/.json
     *
     * @return void
     */
    public function testTopLevelDeletedComment()
    {
        $context = new Context('CommentsSyncTest:testTopLevelDeletedComment');
        $deletedCommentRedditId = 'ebu7bsm';
        $kind = Kind::KIND_LINK;
        $postLink = 'https://www.reddit.com/r/mildlyinfuriating/comments/a6ezwg/your_comment_was_removed_for_being_a_very_short/';

        $content = $this->manager->syncContentFromJsonUrl($context, $kind, $postLink, true);
        $comments = $content->getPost()->getComments();

        $deletedComment = null;
        foreach ($comments as $comment) {
            if ($comment->getRedditId() === $deletedCommentRedditId) {
                $deletedComment = $comment;
            }
        }

        // Assert the Top Level deleted Comment was pulled in.
        $this->assertInstanceOf(Comment::class, $deletedComment);
        $this->assertEquals('[deleted]', $deletedComment->getAuthor());
        $this->assertEquals('[deleted]', $deletedComment->getLatestCommentAuthorText()->getAuthorText()->getText());

        // Assert the deleted Comment's replies were pulled in.
        $replies = $deletedComment->getReplies();
        $this->assertCount(1, $replies);
        $replyComment = $replies->get(0);
        $this->assertEquals('[deleted]', $replyComment->getAuthor());
        $this->assertEquals('I like your thinking.', $replyComment->getLatestCommentAuthorText()->getAuthorText()->getText());
    }

    /**
     * Verify no Comments are synced by default when syncing Contents.
     *
     * @return void
     */
    public function testNoCommentsSynced()
    {
        $context = new Context('CommentsSyncTest:testNoCommentsSynced');
        $fullRedditId = Kind::KIND_LINK . '_' .'vepbt0';

        $content = $this->manager->syncContentFromApiByFullRedditId($context, $fullRedditId);
        $this->assertCount(0, $content->getPost()->getComments());

        $content = $this->manager->syncContentFromApiByFullRedditId($context, $fullRedditId, true);
        $this->assertGreaterThan(60, $content->getPost()->getComments()->count());
    }

    /**
     * Verify all Comments, including those found under "more" can be synced
     * under one function call.
     *
     * @return void
     */
    public function testSyncAllMoreComments()
    {
        $context = new Context('CommentsSyncTest:testSyncAllMoreComments');
        $fullRedditId = Kind::KIND_LINK . '_' .'vepbt0';

        $content = $this->manager->syncContentFromApiByFullRedditId($context, $fullRedditId);
        $this->assertCount(0, $content->getPost()->getComments());

        $comments = $this->commentsManager->syncAllCommentsByContent($context, $content);
        $this->assertGreaterThan(400, $comments->count());

        $allCommentsCount = $this->commentRepository->getTotalPostCount($content->getPost());
        $this->assertGreaterThan(510, $allCommentsCount);
    }

    /**
     * Verify that when Comments are synced with replies, "More" elements are
     * synced as MoreComment Entities related to Comments and can also be
     * synced themselves.
     *
     * @return void
     */
    public function testSyncMoreCommentRelationToComment()
    {
        $context = new Context('CommentsSyncTest:testSyncMoreCommentRelationToComment');
        $fullRedditId = Kind::KIND_LINK . '_' .'vepbt0';

        $content = $this->manager->syncContentFromApiByFullRedditId($context, $fullRedditId);
        $this->assertCount(0, $content->getPost()->getComments());

        $comments = $this->commentsManager->syncCommentsByContent($context, $content);
        $this->assertGreaterThan(70, $comments->count());

        $moreComment = $this->moreCommentRepository->findOneBy(['redditId' => 'icugcmt']);
        $parentComment = $moreComment->getParentComment();
        $this->assertEquals('icryh77', $parentComment->getRedditId());
        $this->assertEquals('Omg my whole family is dying laughing over this one', $parentComment->getLatestCommentAuthorText()->getAuthorText()->getText());

        $comments = $this->commentsManager->syncMoreCommentAndRelatedByRedditId($context, 'icugcmt');
        $this->assertCount(1, $comments);
        $comment = $comments[0];
        $this->assertEquals('icugcmt', $comment->getRedditId());
        $this->assertEquals('"Um, you are going to remember to wash your hands before you eat?"', $comment->getLatestCommentAuthorText()->getAuthorText()->getText());
        // Verify More Comment Entity was purged after sync.
        $moreComment = $this->moreCommentRepository->findOneBy(['redditId' => 'icugcmt']);
        $this->assertEmpty($moreComment);

        // Verify the Comment has been correctly associated to its parent
        // Comment (same parent Comment as the original More Comment Entity
        // above).
        $parentComment = $comment->getParentComment();
        $this->assertInstanceOf(Comment::class, $parentComment);
        $this->assertEquals('icryh77', $parentComment->getRedditId());
        $this->assertEquals('Omg my whole family is dying laughing over this one', $parentComment->getLatestCommentAuthorText()->getAuthorText()->getText());

        // Verify the synced More Comment record was purged after syncing.
        $moreComment = $this->moreCommentRepository->findOneBy(['redditId' => 'icugcmt']);
        $this->assertEmpty($moreComment);

        // Verify on subsequent syncs, a More Comment that is already synced
        // as a Comment is not pulled again as a More Comment.
        $comments = $this->commentsManager->syncCommentsByContent($context, $content);
        $this->assertGreaterThan(70, $comments->count());
        $moreComment = $this->moreCommentRepository->findOneBy(['redditId' => 'icugcmt']);
        $this->assertEmpty($moreComment);
    }

    /**
     * Verify that when a Post is synced with Comments, top-level "More"
     * elements directly associated to the Post are synced as MoreComment
     * Entities related to Comments and can also be synced themselves.
     *
     * @return void
     */
    public function testSyncMoreCommentRelationToPost()
    {
        $this->markTestSkipped('Skipping for now as the test takes more than 5 minutes to complete.');
        $context = new Context('CommentsSyncTest:testSyncMoreCommentRelationToPost');
        $fullRedditId = Kind::KIND_LINK . '_' .'vepbt0';

        $content = $this->manager->syncContentFromApiByFullRedditId($context, $fullRedditId);
        $this->assertCount(0, $content->getPost()->getComments());

        $comments = $this->commentsManager->syncCommentsByContent($context, $content);
        $this->assertGreaterThan(70, $comments->count());

        $moreComment = $this->moreCommentRepository->findOneBy(['redditId' => 'icsbncm']);
        $parentPost = $moreComment->getParentPost();
        $this->assertEquals('vepbt0', $parentPost->getRedditId());
        $this->assertEquals('My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…', $parentPost->getTitle());

        $comments = $this->commentsManager->syncMoreCommentAndRelatedByRedditId($context, 'icsbncm', 20);
        $this->assertGreaterThan(15, $comments);

        $comments = $this->commentsManager->syncMoreCommentAndRelatedByRedditId($context, 'icsbncm', -1);
        $this->assertGreaterThan(300, count($comments));

        $comment = $this->commentRepository->findOneBy(['redditId' => 'icsbncm']);
        $this->assertEquals('icsbncm', $comment->getRedditId());
        $this->assertEquals('It could also pass for a bison plop. Working in Yellowstone, I dodged more than a few of these.', $comment->getLatestCommentAuthorText()->getAuthorText()->getText());
        $this->assertEmpty($comment->getParentComment());

        // Verify the synced More Comment record was purged after syncing.
        $moreComment = $this->moreCommentRepository->findOneBy(['redditId' => 'icsbncm']);
        $this->assertEmpty($moreComment);

        // Verify on subsequent syncs, a More Comment that is already synced
        // as a Comment is not pulled again as a More Comment.
        $comments = $this->commentsManager->syncCommentsByContent($context, $content);
        $this->assertGreaterThan(70, $comments->count());
        $moreComment = $this->moreCommentRepository->findOneBy(['redditId' => 'icsbncm']);
        $this->assertEmpty($moreComment);
    }

    /**
     * Verify Comments be retrieved from a Post, ordered by Upvotes in
     * descending order.
     *
     * Also, verify the ordered Comments are top-level (no Parent Comment)
     * Comments only.
     *
     * @return void
     */
    public function testSortCommentsByUpvotes()
    {
        $context = new Context('CommentsSyncTest:testSortCommentsByUpvotes');
        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_LINK . '_' .'vepbt0');
        $comments = $this->commentsManager->syncCommentsByContent($context, $content);

        $orderedComments = $this->commentsManager->getOrderedCommentsByPost($content->getPost());

        $ordered = true;
        $previousComment = null;
        foreach ($orderedComments as $orderedComment) {
            if (!empty($previousComment)
                && $previousComment->getScore() < $orderedComment->getScore()
            ) {
                $ordered = false;

                // The order has already been detected as incorrect; no need to
                // continue going through the remainder of the list.
                break;
            }

            $this->assertEmpty($orderedComment->getParentComment());
            $previousComment = $orderedComment;
        }

        $this->assertTrue($ordered);
    }

    /**
     * Verify that Comments that have been saved as Content are pushed to the
     * top of the ordered Comments.
     *
     * https://www.reddit.com/r/AskReddit/comments/xjarj9/gamers_of_old_what_will_the_gamers_of_the_modern/ip914eh/
     *
     * @return void
     */
    public function testContentCommentsSortedTop()
    {
        $context = new Context('CommentsSyncTest:testContentCommentsSortedTop');
        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_COMMENT . '_' . 'ip914eh');
        $comments = $this->commentsManager->syncCommentsByContent($context, $content);

        $orderedComments = $this->commentsManager->getOrderedCommentsByPost($content->getPost(), true, true);
        $firstComment = $orderedComments[0];
        $this->assertEquals('ip78grf', $firstComment->getRedditId());

        // Skipping the Content Comment, verify the rest of the Comments array
        // is ordered as expected.
        unset($orderedComments[0]);
        $ordered = true;
        $previousComment = null;

        foreach ($orderedComments as $orderedComment) {
            if (!empty($previousComment)
                && $previousComment->getScore() < $orderedComment->getScore()
            ) {
                $ordered = false;

                // The order has already been detected as incorrect; no need to
                // continue going through the remainder of the list.
                break;
            }

            $this->assertEmpty($orderedComment->getParentComment());
            $previousComment = $orderedComment;
        }

        $this->assertTrue($ordered);
    }
}
