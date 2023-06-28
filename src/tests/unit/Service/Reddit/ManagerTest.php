<?php
declare(strict_types=1);

namespace App\Tests\unit\Service\Reddit;

use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Repository\ContentRepository;
use App\Repository\PostRepository;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager;
use App\Service\Reddit\Manager\Comments;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ManagerTest extends KernelTestCase
{
    private Manager $manager;

    private Comments $commentsManager;

    private ContentRepository $contentRepository;

    private PostRepository $postRepository;

    private CommentRepository $commentRepository;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->commentsManager = $container->get(Comments::class);
        $this->contentRepository = $container->get(ContentRepository::class);
        $this->postRepository = $container->get(PostRepository::class);
        $this->commentRepository = $container->get(CommentRepository::class);
    }

    /**
     * Verify `created_at` timestamp values persisted to the database are in the
     * UTC timezone. Verify this by pulling the values, converting to different
     * Timezones, and ensuring the expected times are correct.
     *
     * https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
     *
     * @return void
     */
    public function testCreatedAtTimeZone()
    {
        $context = new Context('ManagerTest:testCreatedAtTimeZone');
        $redditId = 'vepbt0';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, Kind::KIND_LINK . '_' .$redditId);

        $fetchedPost = $this->postRepository->findOneBy(['redditId' => $content->getPost()->getRedditId()]);

        $postTimestamp = '1655497762';
        $targetDateTime = \DateTimeImmutable::createFromFormat('U', $postTimestamp);
        $targetTimezones = [
            'Europe/Berlin',
            'America/New_York',
            'America/Los_Angeles',
        ];

        // Verify initial UTC timestamp.
        $this->assertEquals('2022-06-17 20:29:22', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals($targetDateTime->format('Y-m-d H:i:s'), $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));

        foreach ($targetTimezones as $targetTimezone) {
            $this->assertEquals(
                $targetDateTime->setTimezone(new \DateTimeZone($targetTimezone))->format('Y-m-d H:i:s'),
                $fetchedPost->getCreatedAt()->setTimezone(new \DateTimeZone($targetTimezone))->format('Y-m-d H:i:s')
            );
        }
    }

    /**
     * There is a different response body provided for a Comment Post when
     * retrieved from the User's Saved history listing.
     *
     * For this test, validate that the Comment Post can still be parsed and
     * persisted when it comes from the Saved history listing.
     *
     * The values used from the assertions come from manually originally calling
     * the API to retrieve the Post response body and parsing to ensure all
     * values are as expected when the body follows the Saved history structure.
     *
     * https://www.reddit.com/r/science/comments/wf1e8p/exercising_almost_daily_for_up_to_an_hour_at_a/iirwrq4/
     *
     * @return void
     */
    public function testParseCommentPostFromSavedListing()
    {
        $context = new Context('ManagerTest:testParseCommentPostFromSavedListing');
        $redditId = 'wf1e8p';

        $savedHistoryRawResponse = file_get_contents('/var/www/mra/tests/resources/sample-json/iirwrq4-from-saved-listing.json');
        $savedHistoryResponse = json_decode($savedHistoryRawResponse, true);

        $content = $this->manager->hydrateContentFromResponseData($context, $savedHistoryResponse['kind'], $savedHistoryResponse);
        $this->contentRepository->add($content, true);
        $comments = $this->commentsManager->syncCommentsByContent($context, $content);

        $fetchedPost = $this->postRepository->findOneBy(['redditId' => $redditId]);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('Exercising almost daily for up to an hour at a low/mid intensity (50-70% heart rate, walking/jogging/cycling) helps reduce fat and lose weight (permanently), restores the body\'s fat balance and has other health benefits related to the body\'s fat and sugar', $fetchedPost->getTitle());
        $this->assertEquals('science', $fetchedPost->getSubreddit()->getName());
        $this->assertEquals('https://www.mdpi.com/2072-6643/14/8/1605/htm', $fetchedPost->getUrl());
        $this->assertEquals('https://www.reddit.com/r/science/comments/wf1e8p/exercising_almost_daily_for_up_to_an_hour_at_a/', $fetchedPost->getRedditPostUrl());

        $kind = $fetchedPost->getContent()->getKind();
        $this->assertInstanceOf(Kind::class, $kind);
        $this->assertEquals(Kind::KIND_COMMENT, $kind->getRedditKindId());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::CONTENT_TYPE_EXTERNAL_LINK, $type->getName());

        // Verify top-level Comments count.
        $this->assertGreaterThan(20, $fetchedPost->getComments()->count());

        // Verify all Comments and Replies count.
        $allCommentsCount = $this->commentRepository->getTotalPostCount($fetchedPost);
        $this->assertGreaterThan(150, $allCommentsCount);
    }

    /**
     * Some Reddit Post URLs can exceed the 255 `varchar` limit for the current
     * `url` column in the `post` table. Due to this, the column should be
     * changed to a `text` column.
     *
     * Verify the target Post can be successfully synced after the table update.
     *
     * Use-case Post: /r/Futurology/comments/102oo0x/stanford_scientists_warn_that_civilization_as_we/
     *
     * @return void
     */
    public function testPostUrlLengthFix(): void
    {
        $context = new Context('ManagerTest:testPostUrlLengthFix');
        $fullRedditId = 't3_102oo0x';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, $fullRedditId);

        // If a Content Entity is returned, the process did not error out
        // during the sync.
        $this->assertInstanceOf(Content::class, $content);
    }
}
