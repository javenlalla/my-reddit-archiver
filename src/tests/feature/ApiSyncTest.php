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
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
     * @param  string|null  $commentAuthorText
     * @param  string|null  $commentAuthorTextRawHtml
     * @param  string|null  $commentAuthorTextHtml
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
        string $commentAuthorText = null,
        string $commentAuthorTextRawHtml = null,
        string $commentAuthorTextHtml = null,
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
            $commentAuthorText,
            $commentAuthorTextRawHtml,
            $commentAuthorTextHtml,
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
     * @param  string|null  $commentAuthorText
     * @param  string|null  $commentAuthorTextRawHtml
     * @param  string|null  $commentAuthorTextHtml
     *
     * @return void
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
        string $commentAuthorText = null,
        string $commentAuthorTextRawHtml = null,
        string $commentAuthorTextHtml = null,
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
            $commentAuthorText,
            $commentAuthorTextRawHtml,
            $commentAuthorTextHtml,
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
     * @param  string|null  $commentAuthorText
     * @param  string|null  $commentAuthorTextRawHtml
     * @param  string|null  $commentAuthorTextHtml
     *
     * @return void
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
        string $commentAuthorText = null,
        string $commentAuthorTextRawHtml = null,
        string $commentAuthorTextHtml = null,
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
            $commentAuthorText,
            $commentAuthorTextRawHtml,
            $commentAuthorTextHtml,
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
                'originalPostUrl' => 'https://www.reddit.com/r/movies/comments/uk7ctt/another_great_thing_about_tremors/',
                'redditId' => 'uk7ctt',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_TEXT,
                'title' => 'Another great thing about Tremors…',
                'subreddit' => 'movies',
                'url' => 'https://www.reddit.com/r/movies/comments/uk7ctt/another_great_thing_about_tremors/',
                'createdAt' => '2022-05-07 06:36:35',
                'authorText' => "The trope of the woman being ignored is exhausting. Movies where the scientists are ignored are also tiring and frustrating. Tremors has no time for it. \n\nRhonda: I think there are three more of these things…\n\nValentine: 3 more???\n\nRhonda: If you look at these seismographs, you’ll see…\n\nEarl: We’ll take your word for it.\n\nAnd off they go. The movie can continue!",
                'authorTextRawHtml' => "&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;The trope of the woman being ignored is exhausting. Movies where the scientists are ignored are also tiring and frustrating. Tremors has no time for it. &lt;/p&gt;\n\n&lt;p&gt;Rhonda: I think there are three more of these things…&lt;/p&gt;\n\n&lt;p&gt;Valentine: 3 more???&lt;/p&gt;\n\n&lt;p&gt;Rhonda: If you look at these seismographs, you’ll see…&lt;/p&gt;\n\n&lt;p&gt;Earl: We’ll take your word for it.&lt;/p&gt;\n\n&lt;p&gt;And off they go. The movie can continue!&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;",
                'authorTextHtml' => "<div class=\"md\"><p>The trope of the woman being ignored is exhausting. Movies where the scientists are ignored are also tiring and frustrating. Tremors has no time for it. </p>\n\n<p>Rhonda: I think there are three more of these things…</p>\n\n<p>Valentine: 3 more???</p>\n\n<p>Rhonda: If you look at these seismographs, you’ll see…</p>\n\n<p>Earl: We’ll take your word for it.</p>\n\n<p>And off they go. The movie can continue!</p>\n</div>",
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
                'originalPostUrl' => 'https://www.reddit.com/r/TheSilphRoad/comments/10zrjou/my_new_stunlock_smeargle/j84z4vm/',
                'redditId' => '10zrjou',
                'type' => Kind::KIND_COMMENT,
                'contentType' => Type::CONTENT_TYPE_VIDEO,
                'title' => 'My new Stun-Lock Smeargle!',
                'subreddit' => 'TheSilphRoad',
                'url' => 'https://www.reddit.com/r/TheSilphRoad/comments/10zrjou/my_new_stunlock_smeargle/',
                'createdAt' => '2023-02-11 16:30:26',
                'authorText' => null,
                'authorTextRawHtml' => null,
                'authorTextHtml' => null,
                'redditPostUrl' => null,
                'gifUrl' => null,
                'commentRedditId' => 'j84z4vm',
                'commentAuthorText' => "You can take out the leaders and Giovanni with it... Take a picture of Shadow Registeel or Porygon. As long as you have a 35 or 40 energy charge move, this will work.    \n\nI didn't do the leaders because I still have 3 more pieces to get before I can do one.",
                'commentAuthorTextRawHtml' => "&lt;div class=\"md\"&gt;&lt;p&gt;You can take out the leaders and Giovanni with it... Take a picture of Shadow Registeel or Porygon. As long as you have a 35 or 40 energy charge move, this will work.    &lt;/p&gt;\n\n&lt;p&gt;I didn&amp;#39;t do the leaders because I still have 3 more pieces to get before I can do one.&lt;/p&gt;\n&lt;/div&gt;",
                'commentAuthorTextHtml' => "<div class=\"md\"><p>You can take out the leaders and Giovanni with it... Take a picture of Shadow Registeel or Porygon. As long as you have a 35 or 40 energy charge move, this will work.    </p>\n\n<p>I didn't do the leaders because I still have 3 more pieces to get before I can do one.</p>\n</div>",
            ],
            'GIF Post' => [
                'originalPostUrl' => 'https://www.reddit.com/r/SquaredCircle/comments/8ung3q/when_people_tell_me_that_wrestling_is_fake_i/',
                'redditId' => '8ung3q',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_GIF,
                'title' => "When people tell me that wrestling is 'fake', I always show them this gif.",
                'subreddit' => 'SquaredCircle',
                'url' => 'http://i.imgur.com/RWFWUYi.gif',
                'createdAt' => '2018-06-28 21:12:06',
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
                'originalPostUrl' => 'https://www.reddit.com/r/css/comments/8vkdsq/the_complete_css_flex_box_tutorial_javascript/',
                'redditId' => '8vkdsq',
                'type' => Kind::KIND_LINK,
                'contentType' => Type::CONTENT_TYPE_EXTERNAL_LINK,
                'title' => 'The Complete CSS Flex Box Tutorial – JavaScript Teacher – Medium',
                'subreddit' => 'css',
                'url' => 'https://medium.com/@js_tut/the-complete-css-flex-box-tutorial-d17971950bdc',
                'createdAt' => '2018-07-02 17:15:14',
                'authorText' => null,
                'authorTextRawHtml' => null,
                'authorTextHtml' => null,
                'redditPostUrl' => 'https://www.reddit.com/r/css/comments/8vkdsq/the_complete_css_flex_box_tutorial_javascript/',
            ],
            'Comment Post (Several Levels Deep)' => [
                'originalPostUrl' => 'https://www.reddit.com/r/AskReddit/comments/xjarj9/gamers_of_old_what_will_the_gamers_of_the_modern/ip914eh/',
                'redditId' => 'xjarj9',
                'type' => Kind::KIND_COMMENT,
                'contentType' => Type::CONTENT_TYPE_TEXT,
                'title' => 'Gamers of old, what will the gamers of the modern console generation never be able to experience?',
                'subreddit' => 'AskReddit',
                'url' => 'https://www.reddit.com/r/AskReddit/comments/xjarj9/gamers_of_old_what_will_the_gamers_of_the_modern/',
                'createdAt' => '2022-09-20 14:46:24',
                'authorText' => null,
                'authorTextRawHtml' => null,
                'authorTextHtml' => null,
                'redditPostUrl' => 'https://www.reddit.com/r/AskReddit/comments/xjarj9/gamers_of_old_what_will_the_gamers_of_the_modern/',
                'gifUrl' => null,
                'commentRedditId' => 'ip914eh',
                'commentAuthorText' => 'Yeah, that boss really was the pinnacle in that game. I mean it was such a big deal to kill it I remember how I did it more than two decades later.',
                'commentAuthorTextRawHtml' => "&lt;div class=\"md\"&gt;&lt;p&gt;Yeah, that boss really was the pinnacle in that game. I mean it was such a big deal to kill it I remember how I did it more than two decades later.&lt;/p&gt;
&lt;/div&gt;",
                'commentAuthorTextHtml' => "<div class=\"md\"><p>Yeah, that boss really was the pinnacle in that game. I mean it was such a big deal to kill it I remember how I did it more than two decades later.</p>
</div>",
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
     * @param  string|null  $commentAuthorText
     * @param  string|null  $commentAuthorTextRawHtml
     * @param  string|null  $commentAuthorTextHtml
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
        string $commentAuthorText = null,
        string $commentAuthorTextRawHtml = null,
        string $commentAuthorTextHtml = null,
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
            $comment = $content->getComment();
            $this->assertEquals($commentRedditId, $comment->getRedditId());

            $targetText = $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getText();
            $this->assertEquals($commentAuthorText, $targetText);

            $targetText = $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getTextRawHtml();
            $this->assertEquals($commentAuthorTextRawHtml, $targetText);

            $targetText = $comment->getCommentAuthorTexts()->get(0)->getAuthorText()->getTextHtml();
            $this->assertEquals($commentAuthorTextHtml, $targetText);
        }
    }
}
