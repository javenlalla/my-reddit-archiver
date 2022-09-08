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
        $this->assertEquals('x00001', $posts[0]['reddit_id']);
        $this->assertEquals('[OC] All of Vegeta transformation', $posts[0]['title']);
        $this->assertEquals('t3', $posts[0]['type']);
        $this->assertEquals('video', $posts[0]['content_type']);
        $this->assertEquals(2532, $posts[0]['score']);
        $this->assertEquals('https://v.redd.it/583ysgfxs8l91/DASH_1080.mp4?source=fallback', $posts[0]['url']);
        $this->assertEquals('RedditUserx00001', $posts[0]['author']);
        $this->assertEquals('dbz', $posts[0]['subreddit']);
        $this->assertEquals('x10001', $posts[0]['reddit_post_id']);
        $this->assertEquals('https://reddit.com//r/dbz/comments/x36nq6/oc_all_of_vegeta_transformation/', $posts[0]['reddit_post_url']);
        $this->assertNull($posts[0]['author_text']);
        $this->assertNull($posts[0]['author_text_html']);
        $this->assertNull($posts[0]['author_text_raw_html']);
    }
}
