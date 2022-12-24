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
        $this->markTestSkipped('Skpping until API response contract has updated to reflect current data structure.');
        $client = static::createClient();

        $crawler = $client->request('GET', '/api/posts');
        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $response);

        $posts = $response['data'];

        // Filter for targeted Post for this test.
        $post = null;
        foreach ($posts as $responsePost) {
            if ($responsePost['reddit_id'] === 'x00001') {
                $post = $responsePost;
            }
        }

        $this->assertEquals('x00001', $post['reddit_id']);
        $this->assertEquals('[OC] All of Vegeta transformation', $post['title']);
        $this->assertEquals('t3', $post['type']);
        $this->assertEquals('video', $post['content_type']);
        $this->assertEquals(2532, $post['score']);
        $this->assertEquals('https://v.redd.it/583ysgfxs8l91/DASH_1080.mp4?source=fallback', $post['url']);
        $this->assertEquals('RedditUserx00001', $post['author']);
        $this->assertEquals('dbz', $post['subreddit']);
        $this->assertEquals('x10001', $post['reddit_post_id']);
        $this->assertEquals('https://reddit.com//r/dbz/comments/x36nq6/oc_all_of_vegeta_transformation/', $post['reddit_post_url']);
        $this->assertNull($post['author_text']);
        $this->assertNull($post['author_text_html']);
        $this->assertNull($post['author_text_raw_html']);

        $comments = $post['comments'];
        $this->assertNotEmpty($comments);

        // Verify the first Comment is present and contains two replies.
        $comment = $comments[0];
        $this->assertEquals('xc0001', $comment['reddit_id']);
        $this->assertEquals('Holy shit! How much time and work did this take?!', $comment['text']);
        $this->assertEquals('Commenterx00001', $comment['author']);
        $this->assertEquals(95, $comment['score']);
        $this->assertEquals(0, $comment['depth']);
        $this->assertCount(2, $comment['replies']);

        // Verify the second Comment is present and contains one reply which
        // also contains a reply.
        $comment = $comments[1];
        $this->assertCount(1, $comment['replies']);
        $this->assertCount(1, $comment['replies'][0]['replies']);
    }
}
