<?php

namespace App\Tests\unit\Controller\API;

use App\Entity\Kind;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ContentsControllerTest extends WebTestCase
{
    /**
     * Verify the expected data-points in the API response for a Contents
     * listing.
     *
     * @return void
     */
    public function testGetContents(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/api/contents');
        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $response);

        $contents = $response['data'];

        // Filter for targeted Content for this test.
        $content = null;
        foreach ($contents as $responsePost) {
            if ($responsePost['post']['reddit_id'] === 'x00001') {
                $content = $responsePost;
            }
        }

        $kind = $content['kind'];
        $this->assertEquals(Kind::KIND_LINK, $kind['reddit_id']);
        $this->assertEquals('Link', $kind['name']);

        $post = $content['post'];
        $this->assertEquals('x00001', $post['reddit_id']);
        $this->assertEquals('[OC] All of Vegeta transformation', $post['title']);
        $this->assertEquals('video', $post['type']);
        $this->assertEquals(2532, $post['score']);
        $this->assertEquals('https://v.redd.it/583ysgfxs8l91/DASH_1080.mp4?source=fallback', $post['url']);
        $this->assertEquals('RedditUserx00001', $post['author']);
        $this->assertEquals('dbz', $post['subreddit']);
        $this->assertEquals('https://reddit.com//r/dbz/comments/x36nq6/oc_all_of_vegeta_transformation/', $post['reddit_url']);
        $this->assertNull($post['author_text']);

        $this->assertEquals(6, $post['comments_count']);
        $comments = $post['comments'];
        $this->assertCount(2, $comments);

        // Verify the first Comment is present and contains two replies.
        $comment = $comments[0];
        $this->assertEquals('xc0001', $comment['reddit_id']);
        $authorText = $comment['author_text'];
        $this->assertEquals('Holy shit! How much time and work did this take?!', $authorText['text']);
        $this->assertEquals('Commenterx00001', $comment['author']);
        $this->assertEquals(95, $comment['score']);
        $this->assertEquals(0, $comment['depth']);
        $this->assertCount(2, $comment['replies']);

        // Verify the second Comment is present and contains one reply which
        // also contains a reply.
        $comment = $comments[1];
        $this->assertCount(1, $comment['replies']);
        $this->assertCount(1, $comment['replies'][0]['replies']);

        $this->assertNull($post['thumbnail']);
        $this->assertIsArray($post['media_assets']);
        $this->assertEmpty($post['media_assets']);
    }
}
