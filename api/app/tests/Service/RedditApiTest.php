<?php

namespace App\Tests\Service;

use App\Entity\Type;
use App\Service\RedditApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RedditApiTest extends KernelTestCase
{
    private RedditApi $redditApi;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->redditApi = $container->get(RedditApi::class);
    }

    public function testArticleComments()
    {
        $postId = 'vlyukg';
        // $testJson = json_decode(file_get_contents(dirname(__FILE__)
        //     .'/../data/image_post.json'), true);

        // (1) boot the Symfony kernel
        // self::bootKernel();

        // (2) use static::getContainer() to access the service container
        $container = static::getContainer();

        // (3) run some service & test the result
        // $newsletterGenerator = $container->get(NewsletterGenerator::class);
        // $newsletter = $newsletterGenerator->generateMonthlyNews(...);

        /** @var RedditApi $redditApi */
        // $redditApi = $container->get(RedditApi::class);
        // $comments = $redditApi->getCommentsByPostId($postId);
        //
        // // $this->assertEquals('...', $newsletter->getContent());
        // $this->assertNotEmpty($comments);
    }

    public function testParseImagePost()
    {
        //https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
        $postResponseData = $this->redditApi->getPostByRedditId(Type::TYPE_LINK, 'vepbt0');
        $this->assertIsArray($postResponseData);

        $postData = $postResponseData['data']['children'][0]['data'];
        $this->assertEquals('vepbt0', $postData['id']);
        $this->assertEquals('My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…', $postData['title']);
        $this->assertEquals('https://i.imgur.com/ThRMZx5.jpg', $postData['url']);
    }
    public function testParseTextPost(){} //https://www.reddit.com/r/German/comments/vlyukg/if_you_are_an_intermediate_level_learner_i/
    public function testParseVideoPost(){} //https://www.reddit.com/r/golang/comments/v443nh/golang_tutorial_how_to_implement_concurrency_with/
    public function testParseGalleryPost(){} //https://www.reddit.com/r/Tremors/comments/v27nr7/all_my_recreations_of_magazine_covers_from/
    public function testParseCommentPost(){} //https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/ia1smh6/?context=3 Use parent_id to get actual Post.
}