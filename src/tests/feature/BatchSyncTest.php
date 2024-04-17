<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\Type;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager\BatchSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group ci-tests
 */
class BatchSyncTest extends KernelTestCase
{
    private BatchSync $batchSyncManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->batchSyncManager = $container->get(BatchSync::class);
    }

    /**
     * Verify a group of Reddit IDs can be synced within a singular batch call.
     *
     * @dataProvider batchSyncDataProvider()
     *
     * @return void
     */
    public function testBatchSync(array $expectedContents = [])
    {
        $context = new Context('BatchSyncTest:testBatchSync');
        $redditIds = [
            't3_vepbt0',
            't3_uk7ctt',
            't1_j84z4vm',
        ];

        $contents = $this->batchSyncManager->batchSyncContentsByRedditIds($context, $redditIds);
        $this->assertInstanceOf(Content::class, $contents[0]);

        foreach ($contents as $content) {
            if (empty($content->getComment())) {
                $expectedContent = $expectedContents['t3_'. $content->getPost()->getRedditId()];
            } else {
                $expectedContent = $expectedContents['t1_'. $content->getComment()->getRedditId()];
            }

            $kind = $content->getKind();
            $this->assertInstanceOf(Kind::class, $kind);
            $this->assertEquals($expectedContent['kind'], $kind->getRedditKindId());

            $contentType = $content->getPost()->getType();
            $this->assertInstanceOf(Type::class, $contentType);
            $this->assertEquals($expectedContent['contentType'], $contentType->getName());

            $post = $content->getPost();
            $this->assertEquals($expectedContent['title'], $post->getTitle());
            $this->assertEquals($expectedContent['url'], $post->getUrl());
        }
    }

    /**
     * Return the data expected for verification in the testBatchSync() test.
     *
     * @return array[]
     */
    public function batchSyncDataProvider(): array
    {
        return [
            [
                [
                    't3_vepbt0' => [
                        'originalPostUrl' => 'https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/',
                        'kind' => Kind::KIND_LINK,
                        'contentType' => Type::CONTENT_TYPE_IMAGE,
                        'title' => 'My sister-in-law made vegetarian meat loaf. Apparently no loaf pans were available…',
                        'subreddit' => 'shittyfoodporn',
                        'url' => 'https://i.imgur.com/ThRMZx5.jpg',
                        'createdAt' => '2022-06-17 20:29:22',
                    ],
                    't3_uk7ctt' => [
                        'originalPostUrl' => 'https://www.reddit.com/r/movies/comments/uk7ctt/another_great_thing_about_tremors/',
                        'redditId' => 'uk7ctt',
                        'kind' => Kind::KIND_LINK,
                        'contentType' => Type::CONTENT_TYPE_TEXT,
                        'title' => 'Another great thing about Tremors…',
                        'subreddit' => 'movies',
                        'url' => 'https://www.reddit.com/r/movies/comments/uk7ctt/another_great_thing_about_tremors/',
                        'createdAt' => '2022-06-27 16:00:42',
                        'authorText' => "The trope of the woman being ignored is exhausting. Movies where the scientists are ignored are also tiring and frustrating. Tremors has no time for it. \n\nRhonda: I think there are three more of these things…\n\nValentine: 3 more???\n\nRhonda: If you look at these seismographs, you’ll see…\n\nEarl: We’ll take your word for it.\n\nAnd off they go. The movie can continue!",
                    ],
                    't1_j84z4vm' => [
                        'originalPostUrl' => 'https://www.reddit.com/r/TheSilphRoad/comments/10zrjou/my_new_stunlock_smeargle/j84z4vm/',
                        'redditId' => '10zrjou',
                        'kind' => Kind::KIND_COMMENT,
                        'contentType' => Type::CONTENT_TYPE_VIDEO,
                        'title' => 'My new Stun-Lock Smeargle!',
                        'subreddit' => 'TheSilphRoad',
                        'url' => 'https://v.redd.it/xkmtttug6lha1',
                        'createdAt' => '2022-05-26 09:36:55',
                        'commentRedditId' => 'j84z4vm',
                    ],
                ]
            ]
        ];
    }
}
