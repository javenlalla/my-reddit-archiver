<?php

namespace App\Tests\Feature;

use App\Entity\ContentType;
use App\Entity\Post;
use App\Entity\Type;
use App\Service\Reddit\Hydrator;
use App\Service\Reddit\Manager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JsonUrlSyncTest extends KernelTestCase
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
}
