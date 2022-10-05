<?php

namespace App\Tests\Feature;

use App\Entity\ContentType;
use App\Entity\Post;
use App\Entity\Type;
use App\Service\Reddit\Manager;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiSyncTest extends KernelTestCase
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
     * https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
     *
     * @return void
     */
    public function testGetPostFromApiByRedditId()
    {
        $redditId = 'vepbt0';
        $post = $this->manager->getPostFromApiByRedditId(Type::TYPE_LINK, $redditId);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals($redditId, $post->getRedditId());
        $this->assertEquals('My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…', $post->getTitle());
        $this->assertEquals('https://i.imgur.com/ThRMZx5.jpg', $post->getUrl());
    }

    /**
     * @dataProvider getSyncPostsData()
     *
     * @param  string  $redditId
     * @param  string  $type
     * @param  string  $contentType
     * @param  string  $title
     * @param  string  $subreddit
     * @param  string  $url
     * @param  string  $createdAt
     *
     * @return void
     * @throws Exception
     */
    public function testSyncPostsFromApi(
        string $originalPostUrl,
        string $redditId,
        string $type,
        string $contentType,
        string $title,
        string $subreddit,
        string $url,
        string $createdAt,
        string $authorText = null,
        string $authorTextRawHtml = null,
        string $authorTextHtml = null,
        string $redditPostUrl = null,
    ) {
        $this->validatePost(
            $this->manager->syncPostByFullRedditId($type . '_' . $redditId),
            $originalPostUrl,
            $redditId,
            $type,
            $contentType,
            $title,
            $subreddit,
            $url,
            $createdAt,
            $authorText,
            $authorTextRawHtml,
            $authorTextHtml,
            $redditPostUrl
        );
    }

    /**
     * @dataProvider getSyncPostsData()
     *
     * @param  string  $redditId
     * @param  string  $type
     * @param  string  $contentType
     * @param  string  $title
     * @param  string  $subreddit
     * @param  string  $url
     * @param  string  $createdAt
     *
     * @return void
     * @throws Exception
     */
    public function testSyncPostsFromJsonUrls(
        string $originalPostUrl,
        string $redditId,
        string $type,
        string $contentType,
        string $title,
        string $subreddit,
        string $url,
        string $createdAt,
        string $authorText = null,
        string $authorTextRawHtml = null,
        string $authorTextHtml = null,
        string $redditPostUrl = null,
    ) {
        $this->validatePost(
            $this->manager->syncPostFromJsonUrl($type, $originalPostUrl),
            $originalPostUrl,
            $redditId,
            $type,
            $contentType,
            $title,
            $subreddit,
            $url,
            $createdAt,
            $authorText,
            $authorTextRawHtml,
            $authorTextHtml,
            $redditPostUrl
        );
    }

    public function getSyncPostsData(): array
    {
        return [
            'Image Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/',
                'redditId' => 'vepbt0',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_IMAGE,
                'title' => 'My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…',
                'subreddit' => 'shittyfoodporn',
                'url' => 'https://i.imgur.com/ThRMZx5.jpg',
                'createdAt' => '2022-06-17 20:29:22',
            ],
            'Reddit-hosted Image Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/coolguides/comments/won0ky/i_learned_how_to_whistle_from_this_in_less_than_5/',
                'redditId' => 'won0ky',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_IMAGE,
                'title' => 'I learned how to whistle from this in less than 5 minutes.',
                'subreddit' => 'coolguides',
                'url' => 'https://i.redd.it/cnfk33iv9sh91.jpg',
                'createdAt' => '2022-08-15 01:52:53',
            ],
            'Text Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/German/comments/vlyukg/if_you_are_an_intermediate_level_learner_i/',
                'redditId' => 'vlyukg',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_TEXT,
                'title' => 'If you are an intermediate level learner, I strongly suggest you give the book "Tintenherz" a try',
                'subreddit' => 'German',
                'url' => 'https://www.reddit.com/r/German/comments/vlyukg/if_you_are_an_intermediate_level_learner_i/',
                'createdAt' => '2022-06-27 16:00:42',
                'authorText' => "I've been reading this book for the past weeks and I'm loving the pace in which I can read it. I feel like it's perfectly suited for B1/B2 level learners (I'd say even A2 learners could read it, albeit in a slower pace).

It is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it's not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.",
                'authorTextRawHtml' => "&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;I&amp;#39;ve been reading this book for the past weeks and I&amp;#39;m loving the pace in which I can read it. I feel like it&amp;#39;s perfectly suited for B1/B2 level learners (I&amp;#39;d say even A2 learners could read it, albeit in a slower pace).&lt;/p&gt;\n\n&lt;p&gt;It is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it&amp;#39;s not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;",
                'authorTextHtml' => "<div class=\"md\"><p>I've been reading this book for the past weeks and I'm loving the pace in which I can read it. I feel like it's perfectly suited for B1/B2 level learners (I'd say even A2 learners could read it, albeit in a slower pace).</p>

<p>It is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it's not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.</p>
</div>",
            ],
            'Text Post With Only Title (No Author Text Or Content)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/AskReddit/comments/vdmg2f/serious_what_should_everyone_learn_how_to_do/',
                'redditId' => 'vdmg2f',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_TEXT,
                'title' => '[serious] What should everyone learn how to do?',
                'subreddit' => 'AskReddit',
                'url' => 'https://www.reddit.com/r/AskReddit/comments/vdmg2f/serious_what_should_everyone_learn_how_to_do/',
                'createdAt' => '2022-06-16 13:48:47',
            ],
            'Video Post (YouTube)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/golang/comments/v443nh/golang_tutorial_how_to_implement_concurrency_with/',
                'redditId' => 'v443nh',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_VIDEO,
                'title' => 'Golang Tutorial | How To Implement Concurrency With Goroutines and Channels',
                'subreddit' => 'golang',
                'url' => 'https://youtu.be/bbgip1-ZbZg',
                'createdAt' => '2022-06-03 17:11:50',
            ],
            'Video Post (Reddit)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/Unexpected/comments/tl8qic/i_think_i_married_a_psychopath/',
                'redditId' => 'tl8qic',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_VIDEO,
                'title' => 'I think I married a psychopath',
                'subreddit' => 'Unexpected',
                'url' => 'https://v.redd.it/8u3caw3zm6p81/DASH_720.mp4?source=fallback',
                'createdAt' => '2022-03-23 19:11:31',
            ],
            'Video Post (Reddit, No Audio)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/ProgrammerHumor/comments/wfylnl/when_you_use_a_new_library_without_reading_the/',
                'redditId' => 'wfylnl',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_VIDEO,
                'title' => 'When you use a new library without reading the documentation',
                'subreddit' => 'ProgrammerHumor',
                'url' => 'https://v.redd.it/bofh9q9jkof91/DASH_720.mp4?source=fallback',
                'createdAt' => '2022-08-04 11:17:29',
            ],
            'Gallery Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/Tremors/comments/v27nr7/all_my_recreations_of_magazine_covers_from/',
                'redditId' => 'v27nr7',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_IMAGE_GALLERY,
                'title' => 'All my recreations of magazine covers from Tremors 2 so far',
                'subreddit' => 'Tremors',
                'url' => 'https://www.reddit.com/gallery/v27nr7',
                'createdAt' => '2022-06-01 03:31:38',
            ],
            'Comment Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/ia1smh6/',
                'redditId' => 'ia1smh6',
                'type' => Type::TYPE_COMMENT,
                'contentType' => ContentType::CONTENT_TYPE_TEXT,
                'title' => 'Passed my telc B2 exam with a great score (275/300). Super stoked about it!',
                'subreddit' => 'German',
                'url' => 'https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/',
                'createdAt' => '2022-05-26 10:42:40',
                'authorText' => 'Congrats! What did your study routine look like leading up to it?',
                'authorTextRawHtml' => "&lt;div class=\"md\"&gt;&lt;p&gt;Congrats! What did your study routine look like leading up to it?&lt;/p&gt;
&lt;/div&gt;",
                'authorTextHtml' => "<div class=\"md\"><p>Congrats! What did your study routine look like leading up to it?</p>
</div>",
            ],
            'GIF Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/me_irl/comments/wgb8wj/me_irl/',
                'redditId' => 'wgb8wj',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_GIF,
                'title' => 'me_irl',
                'subreddit' => 'me_irl',
                'url' => 'https://preview.redd.it/kanpjvgbarf91.gif?format=mp4&s=d3c0bb16145d61e9872bda355b742cfd3031fd69',
                'createdAt' => '2022-08-04 20:25:21',
            ],
            'Text Post With Image' => [
                'originalPostUrl' => 'https://www.reddit.com/r/Tremors/comments/utsmkw/tremors_poster_for_gallery1988',
                'redditId' => 'utsmkw',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_TEXT,
                'title' => 'Tremors poster for Gallery1988',
                'subreddit' => 'Tremors',
                'url' => 'https://www.reddit.com/r/Tremors/comments/utsmkw/tremors_poster_for_gallery1988/',
                'createdAt' => '2022-05-20 11:27:43',
                'authorText' => "I did a poster for Gallery1988 in L.A.  \nI called the artwork \"The floor is lava\"\n\n  \nFor all of you who are interested in a print, here's the link:\n\n[https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230](https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230)\n\nhttps://preview.redd.it/gcj91awy8m091.jpg?width=900&amp;format=pjpg&amp;auto=webp&amp;s=7cab4910712115bb273171653cc754b9077c1455",
                'authorTextRawHtml' => "&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;I did a poster for Gallery1988 in L.A.&lt;br/&gt;\nI called the artwork &amp;quot;The floor is lava&amp;quot;&lt;/p&gt;\n\n&lt;p&gt;For all of you who are interested in a print, here&amp;#39;s the link:&lt;/p&gt;\n\n&lt;p&gt;&lt;a href=\"https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230\"&gt;https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230&lt;/a&gt;&lt;/p&gt;\n\n&lt;p&gt;&lt;a href=\"https://preview.redd.it/gcj91awy8m091.jpg?width=900&amp;amp;format=pjpg&amp;amp;auto=webp&amp;amp;s=7cab4910712115bb273171653cc754b9077c1455\"&gt;https://preview.redd.it/gcj91awy8m091.jpg?width=900&amp;amp;format=pjpg&amp;amp;auto=webp&amp;amp;s=7cab4910712115bb273171653cc754b9077c1455&lt;/a&gt;&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;",
                'authorTextHtml' => "<div class=\"md\"><p>I did a poster for Gallery1988 in L.A.<br/>\nI called the artwork \"The floor is lava\"</p>\n
<p>For all of you who are interested in a print, here's the link:</p>\n
<p><a href=\"https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230\">https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230</a></p>\n
<p><a href=\"https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&s=7cab4910712115bb273171653cc754b9077c1455\">https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&s=7cab4910712115bb273171653cc754b9077c1455</a></p>
</div>",
            ],
            'External Link Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/javascript/comments/urn2yw/mithriljs_release_a_new_version_after_nearly_3/',
                'redditId' => 'urn2yw',
                'type' => Type::TYPE_LINK,
                'contentType' => ContentType::CONTENT_TYPE_EXTERNAL_LINK,
                'title' => 'Mithril.js release a new version after nearly 3 years',
                'subreddit' => 'javascript',
                'url' => 'https://github.com/MithrilJS/mithril.js/releases',
                'createdAt' => '2022-05-17 13:59:01',
                'authorText' => null,
                'authorTextRawHtml' => null,
                'authorTextHtml' => null,
                'redditPostUrl' => 'https://reddit.com/r/javascript/comments/urn2yw/mithriljs_release_a_new_version_after_nearly_3/',
            ],
        ];
    }

    /**
     * @param  Post  $post
     * @param  string  $originalPostUrl
     * @param  string  $redditId
     * @param  string  $type
     * @param  string  $contentType
     * @param  string  $title
     * @param  string  $subreddit
     * @param  string  $url
     * @param  string  $createdAt
     * @param  string|null  $authorText
     * @param  string|null  $authorTextRawHtml
     * @param  string|null  $authorTextHtml
     * @param  string|null  $redditPostUrl
     *
     * @return void
     */
    private function validatePost(
        Post $post,
        string $originalPostUrl,
        string $redditId,
        string $type,
        string $contentType,
        string $title,
        string $subreddit,
        string $url,
        string $createdAt,
        string $authorText = null,
        string $authorTextRawHtml = null,
        string $authorTextHtml = null,
        string $redditPostUrl = null,
    )
    {
        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals($redditId, $post->getRedditId());
        $this->assertEquals($title, $post->getTitle());
        $this->assertEquals($subreddit, $post->getSubreddit());
        $this->assertEquals($url, $post->getUrl());
        $this->assertEquals($createdAt, $post->getCreatedAt()->format('Y-m-d H:i:s'));

        $postType = $post->getType();
        $this->assertInstanceOf(Type::class, $postType);
        $this->assertEquals($type, $postType->getRedditTypeId());

        $postContentType = $post->getContentType();
        $this->assertInstanceOf(ContentType::class, $postContentType);
        $this->assertEquals($contentType, $postContentType->getName());

        if ($authorText === null) {
            $this->assertEmpty($post->getAuthorText());
        } else {
            $this->assertEquals($authorText, $post->getAuthorText());
        }

        if ($authorTextRawHtml === null) {
            $this->assertEmpty($post->getAuthorTextRawHtml());
        } else {
            $this->assertEquals($authorTextRawHtml, $post->getAuthorTextRawHtml());
        }

        if ($authorTextHtml === null) {
            $this->assertEmpty($post->getAuthorTextHtml());
        } else {
            $this->assertEquals($authorTextHtml, $post->getAuthorTextHtml());
        }

        if (!empty($redditPostUrl)) {
            $this->assertEquals($redditPostUrl, $post->getRedditPostUrl());
        }
    }
}
