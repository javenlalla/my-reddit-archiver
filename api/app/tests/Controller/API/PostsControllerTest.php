<?php

namespace App\Tests\Controller\API;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PostsControllerTest extends WebTestCase
{
    /**
     * Simple test to validate a Posts API call returns successfully with the
     * expected structure.
     *
     * @return void
     */
    public function testBasicApiPostsCall()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/api/posts');
        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $response);

        $posts = $response['data'];
        $this->assertEquals('x00001', $posts[0]['redditId']);

    }
}
