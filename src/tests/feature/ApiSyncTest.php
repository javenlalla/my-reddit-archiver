<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Type;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager;
use App\Service\Reddit\Manager\BatchSync;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class ApiSyncTest extends KernelTestCase
{
    private Manager $manager;

    private BatchSync $batchSyncManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->batchSyncManager = $container->get(BatchSync::class);
    }

    /**
     * Verify a basic, single Post sync from the Reddit API.
     *
     * https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/
     *
     * @return void
     */
    public function testBasicSyncFromApi()
    {
        $context = new Context('ApiSyncText:testBasicSyncFromApi');
        $redditId = 't3_vepbt0';
        $content = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);

        $this->assertInstanceOf(Content::class, $content);

        $kind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $kind);
        $this->assertEquals(Kind::KIND_LINK, $kind->getRedditKindId());

        $contentType = $content->getPost()->getType();
        $this->assertInstanceOf(Type::class, $contentType);
        $this->assertEquals(Type::CONTENT_TYPE_IMAGE, $contentType->getName());

        $post = $content->getPost();
        $this->assertEquals('vepbt0', $post->getRedditId());
        $this->assertEquals('My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…', $post->getTitle());
        $this->assertEquals('https://i.imgur.com/ThRMZx5.jpg', $post->getUrl());
    }

    /**
     * @dataProvider getSyncPostsData()
     *
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
     * @param  string|null  $gifUrl
     * @param  string|null  $commentRedditId
     *
     * @return void
     */
    public function testSyncContentsByBatch(
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
        string $gifUrl = null,
        string $commentRedditId = null,
    ) {
        $fullRedditId = $type . '_' . $redditId;
        if (!empty($commentRedditId)) {
            $fullRedditId = $type . '_' . $commentRedditId;
        }

        $context = new Context('ApiSyncText:testSyncContentsByBatch');
        $contents = $this->batchSyncManager->batchSyncContentsByRedditIds($context, [$fullRedditId]);
        $this->assertCount(1, $contents);

        $this->validateContent(
            true,
            $contents[0],
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
            $redditPostUrl,
            $gifUrl,
            $commentRedditId,
        );
    }

    /**
     * @dataProvider getSyncPostsData()
     *
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
     * @param  string|null  $gifUrl
     * @param  string|null  $commentRedditId
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws ExceptionInterface
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
        string $gifUrl = null,
        string $commentRedditId = null,
    ) {
        $fullRedditId = $type . '_' . $redditId;
        if ($type === Kind::KIND_COMMENT) {
            $fullRedditId = $type . '_' . $commentRedditId;
        }

        $context = new Context('ApiSyncText:testSyncPostsFromApi');

        $this->validateContent(
            false,
            $this->manager->syncContentFromApiByFullRedditId($context, $fullRedditId),
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
            $redditPostUrl,
            $gifUrl,
            $commentRedditId,
        );
    }

    /**
     * @dataProvider getSyncPostsData()
     *
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
     * @param  string|null  $gifUrl
     * @param  string|null  $commentRedditId
     *
     * @return void
     * @throws InvalidArgumentException
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
        string $gifUrl = null,
        string $commentRedditId = null,
    ) {
        $context = new Context('ApiSyncText:testSyncPostsFromJsonUrls');

        $this->validateContent(
            true,
            $this->manager->syncContentFromJsonUrl($context, $type, $originalPostUrl),
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
            $redditPostUrl,
            $gifUrl,
            $commentRedditId,
        );
    }

    public function getSyncPostsData(): array
    {
        return [
            'Image Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/',
                'redditId' => 'vepbt0',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_IMAGE,
                'title' => 'My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…',
                'subreddit' => 'shittyfoodporn',
                'url' => 'https://i.imgur.com/ThRMZx5.jpg',
                'createdAt' => '2022-06-17 20:29:22',
            ],
            'Image Post (Reddit-hosted)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/coolguides/comments/won0ky/i_learned_how_to_whistle_from_this_in_less_than_5/',
                'redditId' => 'won0ky',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_IMAGE,
                'title' => 'I learned how to whistle from this in less than 5 minutes.',
                'subreddit' => 'coolguides',
                'url' => 'https://i.redd.it/cnfk33iv9sh91.jpg',
                'createdAt' => '2022-08-15 01:52:53',
            ],
            'Text Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/German/comments/vlyukg/if_you_are_an_intermediate_level_learner_i/',
                'redditId' => 'vlyukg',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_TEXT,
                'title' => 'If you are an intermediate level learner, I strongly suggest you give the book "Tintenherz" a try',
                'subreddit' => 'German',
                'url' => 'https://www.reddit.com/r/German/comments/vlyukg/if_you_are_an_intermediate_level_learner_i/',
                'createdAt' => '2022-06-27 16:00:42',
                'authorText' => "I've been reading this book for the past weeks and I'm loving the pace in which I can read it. I feel like it's perfectly suited for B1/B2 level learners (I'd say even A2 learners could read it, albeit in a slower pace).\n\nIt is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it's not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.",
                'authorTextRawHtml' => "&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;I&amp;#39;ve been reading this book for the past weeks and I&amp;#39;m loving the pace in which I can read it. I feel like it&amp;#39;s perfectly suited for B1/B2 level learners (I&amp;#39;d say even A2 learners could read it, albeit in a slower pace).&lt;/p&gt;\n\n&lt;p&gt;It is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it&amp;#39;s not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;",
                'authorTextHtml' => "<div class=\"md\"><p>I've been reading this book for the past weeks and I'm loving the pace in which I can read it. I feel like it's perfectly suited for B1/B2 level learners (I'd say even A2 learners could read it, albeit in a slower pace).</p>\n\n<p>It is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it's not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.</p>\n</div>",
            ],
            'Text Post With Only Title (No Author Text Or Content)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/AskReddit/comments/vdmg2f/serious_what_should_everyone_learn_how_to_do/',
                'redditId' => 'vdmg2f',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_TEXT,
                'title' => '[serious] What should everyone learn how to do?',
                'subreddit' => 'AskReddit',
                'url' => 'https://www.reddit.com/r/AskReddit/comments/vdmg2f/serious_what_should_everyone_learn_how_to_do/',
                'createdAt' => '2022-06-16 13:48:47',
            ],
            'Video Post (YouTube)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/golang/comments/v443nh/golang_tutorial_how_to_implement_concurrency_with/',
                'redditId' => 'v443nh',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_VIDEO,
                'title' => 'Golang Tutorial | How To Implement Concurrency With Goroutines and Channels',
                'subreddit' => 'golang',
                'url' => 'https://youtu.be/bbgip1-ZbZg',
                'createdAt' => '2022-06-03 17:11:50',
            ],
            'Video Post (Reddit)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/Unexpected/comments/tl8qic/i_think_i_married_a_psychopath/',
                'redditId' => 'tl8qic',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_VIDEO,
                'title' => 'I think I married a psychopath',
                'subreddit' => 'Unexpected',
                'url' => 'https://v.redd.it/8u3caw3zm6p81/DASH_720.mp4?source=fallback',
                'createdAt' => '2022-03-23 19:11:31',
            ],
            'Video Post (Reddit, No Audio)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/ProgrammerHumor/comments/wfylnl/when_you_use_a_new_library_without_reading_the/',
                'redditId' => 'wfylnl',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_VIDEO,
                'title' => 'When you use a new library without reading the documentation',
                'subreddit' => 'ProgrammerHumor',
                'url' => 'https://v.redd.it/bofh9q9jkof91/DASH_720.mp4?source=fallback',
                'createdAt' => '2022-08-04 11:17:29',
            ],
            'Gallery Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/Tremors/comments/v27nr7/all_my_recreations_of_magazine_covers_from/',
                'redditId' => 'v27nr7',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_IMAGE_GALLERY,
                'title' => 'All my recreations of magazine covers from Tremors 2 so far',
                'subreddit' => 'Tremors',
                'url' => 'https://www.reddit.com/gallery/v27nr7',
                'createdAt' => '2022-06-01 03:31:38',
            ],
            'Comment Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/ia1smh6/',
                'redditId' => 'uy3sx1',
                'type' => Kind::KIND_COMMENT,
                'contentType' => Type::CONTENT_TYPE_TEXT,
                'title' => 'Passed my telc B2 exam with a great score (275/300). Super stoked about it!',
                'subreddit' => 'German',
                'url' => 'https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/',
                // 'commentCreatedAt' => '2022-05-26 10:42:40',
                'createdAt' => '2022-05-26 09:36:55',
//                 'authorText' => 'Congrats! What did your study routine look like leading up to it?',
//                 'authorTextRawHtml' => "&lt;div class=\"md\"&gt;&lt;p&gt;Congrats! What did your study routine look like leading up to it?&lt;/p&gt;
// &lt;/div&gt;",
//                 'authorTextHtml' => "<div class=\"md\"><p>Congrats! What did your study routine look like leading up to it?</p>
// </div>",
                'authorText' => 'I’d be glad to offer any advice.',
                'authorTextRawHtml' => "&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;I’d be glad to offer any advice.&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;",
                'authorTextHtml' => "<div class=\"md\"><p>I’d be glad to offer any advice.</p>\n</div>",
                'redditPostUrl' => null,
                'gifUrl' => null,
                'commentRedditId' => 'ia1smh6',
            ],
            'GIF Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/me_irl/comments/wgb8wj/me_irl/',
                'redditId' => 'wgb8wj',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_GIF,
                'title' => 'me_irl',
                'subreddit' => 'me_irl',
                'url' => 'https://preview.redd.it/kanpjvgbarf91.gif?format=mp4&s=d3c0bb16145d61e9872bda355b742cfd3031fd69',
                'createdAt' => '2022-08-04 20:25:21',
            ],
            'GIF Post (Reddit-Hosted)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/SquaredCircle/comments/cs8urd/matt_riddle_got_hit_by_a_truck/',
                'redditId' => 'cs8urd',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_GIF,
                'title' => 'Matt Riddle got hit by a truck',
                'subreddit' => 'SquaredCircle',
                'url' => 'https://preview.redd.it/aha06x6skah31.gif?format=mp4&s=4538ed4f9e9c0cb4d692f7d4a795d64a21447efa',
                'createdAt' => '2019-08-18 23:29:02',
                'authorText' => null,
                'authorTextRawHtml' => null,
                'authorTextHtml' => null,
                'redditPostUrl' => 'https://www.reddit.com/r/SquaredCircle/comments/cs8urd/matt_riddle_got_hit_by_a_truck/',
                'gifUrl' => 'https://i.redd.it/aha06x6skah31.gif',
            ],
            'Text Post With Image' => [
                'originalPostUrl' => 'https://www.reddit.com/r/Tremors/comments/utsmkw/tremors_poster_for_gallery1988',
                'redditId' => 'utsmkw',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_TEXT,
                'title' => 'Tremors poster for Gallery1988',
                'subreddit' => 'Tremors',
                'url' => 'https://www.reddit.com/r/Tremors/comments/utsmkw/tremors_poster_for_gallery1988/',
                'createdAt' => '2022-05-20 11:27:43',
                'authorText' => "I did a poster for Gallery1988 in L.A.  \nI called the artwork \"The floor is lava\"\n\n  \nFor all of you who are interested in a print, here's the link:\n\n[https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230](https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230)\n\nhttps://preview.redd.it/gcj91awy8m091.jpg?width=900&amp;format=pjpg&amp;auto=webp&amp;v=enabled&amp;s=8a5a16f886e24f206b0dbea9cc0e5a6cd25ef018",
                'authorTextRawHtml' => "&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;I did a poster for Gallery1988 in L.A.&lt;br/&gt;\nI called the artwork &amp;quot;The floor is lava&amp;quot;&lt;/p&gt;\n\n&lt;p&gt;For all of you who are interested in a print, here&amp;#39;s the link:&lt;/p&gt;\n\n&lt;p&gt;&lt;a href=\"https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230\"&gt;https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230&lt;/a&gt;&lt;/p&gt;\n\n&lt;p&gt;&lt;a href=\"https://preview.redd.it/gcj91awy8m091.jpg?width=900&amp;amp;format=pjpg&amp;amp;auto=webp&amp;amp;v=enabled&amp;amp;s=8a5a16f886e24f206b0dbea9cc0e5a6cd25ef018\"&gt;https://preview.redd.it/gcj91awy8m091.jpg?width=900&amp;amp;format=pjpg&amp;amp;auto=webp&amp;amp;v=enabled&amp;amp;s=8a5a16f886e24f206b0dbea9cc0e5a6cd25ef018&lt;/a&gt;&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;",
                'authorTextHtml' => "<div class=\"md\"><p>I did a poster for Gallery1988 in L.A.<br/>\nI called the artwork \"The floor is lava\"</p>\n\n<p>For all of you who are interested in a print, here's the link:</p>\n\n<p><a href=\"https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230\">https://nineteeneightyeight.com/products/edgar-ascensao-the-floor-is-lava-print?variant=41801538732230</a></p>\n\n<p><a href=\"https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&v=enabled&s=8a5a16f886e24f206b0dbea9cc0e5a6cd25ef018\">https://preview.redd.it/gcj91awy8m091.jpg?width=900&format=pjpg&auto=webp&v=enabled&s=8a5a16f886e24f206b0dbea9cc0e5a6cd25ef018</a></p>\n</div>",
            ],
            'External Link Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/javascript/comments/urn2yw/mithriljs_release_a_new_version_after_nearly_3/',
                'redditId' => 'urn2yw',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_EXTERNAL_LINK,
                'title' => 'Mithril.js release a new version after nearly 3 years',
                'subreddit' => 'javascript',
                'url' => 'https://github.com/MithrilJS/mithril.js/releases',
                'createdAt' => '2022-05-17 13:59:01',
                'authorText' => null,
                'authorTextRawHtml' => null,
                'authorTextHtml' => null,
                'redditPostUrl' => 'https://www.reddit.com/r/javascript/comments/urn2yw/mithriljs_release_a_new_version_after_nearly_3/',
            ],
            'Comment Post (Several Levels Deep)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/AskReddit/comments/xjarj9/gamers_of_old_what_will_the_gamers_of_the_modern/ip914eh/',
                'redditId' => 'xjarj9',
                'type' => Kind::KIND_COMMENT,
                'contentType' => Type::CONTENT_TYPE_TEXT,
                'title' => 'Gamers of old, what will the gamers of the modern console generation never be able to experience?',
                'subreddit' => 'AskReddit',
                'url' => 'https://www.reddit.com/r/AskReddit/comments/xjarj9/gamers_of_old_what_will_the_gamers_of_the_modern/',
                // 'commentCreatedAt' => '2022-09-20 21:45:38',
                'createdAt' => '2022-09-20 14:46:24',
                'authorText' => null,
                'authorTextRawHtml' => null,
                'authorTextHtml' => null,
//                 'authorText' => 'Yeah, that boss really was the pinnacle in that game. I mean it was such a big deal to kill it I remember how I did it more than two decades later.',
//                 'authorTextRawHtml' => "&lt;div class=\"md\"&gt;&lt;p&gt;Yeah, that boss really was the pinnacle in that game. I mean it was such a big deal to kill it I remember how I did it more than two decades later.&lt;/p&gt;
// &lt;/div&gt;",
//                 'authorTextHtml' => "<div class=\"md\"><p>Yeah, that boss really was the pinnacle in that game. I mean it was such a big deal to kill it I remember how I did it more than two decades later.</p>
// </div>",
                'redditPostUrl' => 'https://www.reddit.com/r/AskReddit/comments/xjarj9/gamers_of_old_what_will_the_gamers_of_the_modern/',
                'gifUrl' => null,
                'commentRedditId' => 'ip914eh',
            ],
        ];
    }

    /**
     * @param  bool  $jsonUrl
     * @param  Content  $content
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
     * @param  string|null  $gifUrl
     * @param  string|null  $commentRedditId
     *
     * @return void
     */
    private function validateContent(
        bool $jsonUrl,
        Content $content,
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
        string $gifUrl = null,
        string $commentRedditId = null,
    )
    {
        $post = $content->getPost();

        // This logic is needed due to Reddit-hosted GIFs having a `preview`
        // element in the API response but not in the JSON URL. So a distinction
        // is required in the URL validation here based on where the response is
        // retrieved from.
        $targetUrl = $post->getUrl();
        if ($jsonUrl === true && !empty($gifUrl)) {
            $targetUrl = $gifUrl;
        }

        $this->assertInstanceOf(Post::class, $post);
        $this->assertNotEmpty($post->getId());
        $this->assertEquals($redditId, $post->getRedditId());
        $this->assertEquals($title, $post->getTitle());
        $this->assertEquals($subreddit, $post->getSubreddit()->getName());
        $this->assertEquals($targetUrl, $post->getUrl());
        $this->assertEquals($createdAt, $post->getCreatedAt()->format('Y-m-d H:i:s'));

        $contentKind = $content->getKind();
        $this->assertInstanceOf(Kind::class, $contentKind);
        $this->assertEquals($type, $contentKind->getRedditKindId());

        $type = $post->getType();
        $this->assertInstanceOf(Type::class, $type);
        $this->assertEquals($contentType, $type->getName());

        if ($authorText === null) {
            $this->assertEmpty($post->getPostAuthorTexts());
        } else {
            $targetText = $post->getPostAuthorTexts()->get(0)->getAuthorText()->getText();
            $this->assertEquals($authorText, $targetText);
        }

        if ($authorTextRawHtml === null) {
            $this->assertEmpty($post->getPostAuthorTexts());
        } else {
            $targetText = $post->getPostAuthorTexts()->get(0)->getAuthorText()->getTextRawHtml();
            $this->assertEquals($authorTextRawHtml, $targetText);
        }

        if ($authorTextHtml === null) {
            $this->assertEmpty($post->getPostAuthorTexts());
        } else {
            $targetText = $post->getPostAuthorTexts()->get(0)->getAuthorText()->getTextHtml();
            $this->assertEquals($authorTextHtml, $targetText);
        }

        if (!empty($redditPostUrl)) {
            $this->assertEquals($redditPostUrl, $post->getRedditPostUrl());
        }

        if (!empty($commentRedditId)) {
            $this->assertEquals($commentRedditId, $content->getComment()->getRedditId());
        }
    }
}
