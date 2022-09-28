<?php

namespace App\Tests\Service\Reddit;

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
        $redditId = 'vepbt0';
        $post = $this->manager->getPostFromApiByRedditId(Hydrator::TYPE_LINK, $redditId);

        $this->manager->savePost($post);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);

        $postTimestamp = 1655497762;
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
        $redditId = 'iirwrq4';

        $savedHistoryRawResponse = file_get_contents('/var/www/mra-api/tests/resources/sample-json/iirwrq4-from-saved-listing.json');
        $savedHistoryResponse = json_decode($savedHistoryRawResponse, true);

        $syncedPost = $this->manager->syncPost($savedHistoryResponse);

        $fetchedPost = $this->manager->getPostByRedditId($redditId);
        $this->assertInstanceOf(Post::class, $fetchedPost);
        $this->assertNotEmpty($fetchedPost->getId());
        $this->assertEquals($redditId, $fetchedPost->getRedditId());
        $this->assertEquals('Exercising almost daily for up to an hour at a low/mid intensity (50-70% heart rate, walking/jogging/cycling) helps reduce fat and lose weight (permanently), restores the body\'s fat balance and has other health benefits related to the body\'s fat and sugar', $fetchedPost->getTitle());
        $this->assertEquals('science', $fetchedPost->getSubreddit());
        $this->assertEquals('https://www.mdpi.com/2072-6643/14/8/1605/htm', $fetchedPost->getUrl());
        $this->assertEquals('https://reddit.com//r/science/comments/wf1e8p/exercising_almost_daily_for_up_to_an_hour_at_a/', $fetchedPost->getRedditPostUrl());

        $this->assertEquals('2022-08-03 12:43:19', $fetchedPost->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals("I've recently started running after not running for 10+ years. This was the single biggest piece of advice I got.\n
Get a good heartrate monitor and don't go above 150. Just maintain 140-150. I was shocked at how much longer I could run for. I hadn't run since highschool and I ran a 5k cold turkey. It was a slow 5k but I ran the whole time. Pace is everything.", $fetchedPost->getAuthorText());

        $this->assertEquals("&lt;div class=\"md\"&gt;&lt;p&gt;I&amp;#39;ve recently started running after not running for 10+ years. This was the single biggest piece of advice I got.&lt;/p&gt;\n
&lt;p&gt;Get a good heartrate monitor and don&amp;#39;t go above 150. Just maintain 140-150. I was shocked at how much longer I could run for. I hadn&amp;#39;t run since highschool and I ran a 5k cold turkey. It was a slow 5k but I ran the whole time. Pace is everything.&lt;/p&gt;
&lt;/div&gt;", $fetchedPost->getAuthorTextRawHtml());

        $this->assertEquals("<div class=\"md\"><p>I've recently started running after not running for 10+ years. This was the single biggest piece of advice I got.</p>\n
<p>Get a good heartrate monitor and don't go above 150. Just maintain 140-150. I was shocked at how much longer I could run for. I hadn't run since highschool and I ran a 5k cold turkey. It was a slow 5k but I ran the whole time. Pace is everything.</p>
</div>", $fetchedPost->getAuthorTextHtml());

        $type = $fetchedPost->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals(Type::TYPE_COMMENT, $type->getRedditTypeId());

        $contentType = $fetchedPost->getContentType();
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertEquals(ContentType::CONTENT_TYPE_TEXT, $contentType->getName());

        // Verify top-level Comments count.
        $this->assertCount(524, $fetchedPost->getComments());

        // Verify all Comments and Replies count.
        $allCommentsCount = $this->manager->getAllCommentsCountFromPost($fetchedPost);
        $this->assertEquals(1347, $allCommentsCount);
    }
}
