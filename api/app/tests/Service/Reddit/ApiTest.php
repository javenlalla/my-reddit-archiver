<?php

namespace App\Tests\Service\Reddit;

use App\Entity\Type;
use App\Service\Reddit\Api;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiTest extends KernelTestCase
{
    private Api $api;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->api = $container->get(Api::class);
    }

    /**
     * Verify basic API Comments retrieval by Post Reddit ID.
     *
     * @return void
     */
    public function testPostComments()
    {
        $redditId = 'vlyukg';

        $comments = $this->api->getPostCommentsByRedditId($redditId);
        $this->assertNotEmpty($comments);
    }

    public function testParseImagePost()
    {
        //https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
        $postResponseData = $this->api->getPostByRedditId(Type::TYPE_LINK, 'vepbt0');
        $this->assertIsArray($postResponseData);

        $postData = $postResponseData['data']['children'][0]['data'];
        $this->assertEquals('vepbt0', $postData['id']);
        $this->assertEquals('My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…', $postData['title']);
        $this->assertEquals('https://i.imgur.com/ThRMZx5.jpg', $postData['url']);
    }
}
