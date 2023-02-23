<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\Type;
use App\Service\Reddit\Manager\BatchSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
        $redditIds = [
            't3_vepbt0',
            't3_vlyukg',
            't1_ia1smh6',
        ];

        $contents = $this->batchSyncManager->batchSyncContentsByRedditIds($redditIds);
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
                    't3_vlyukg' => [
                        'originalPostUrl' => 'https://www.reddit.com/r/German/comments/vlyukg/if_you_are_an_intermediate_level_learner_i/',
                        'redditId' => 'vlyukg',
                        'kind' => Kind::KIND_LINK,
                        'contentType' => Type::CONTENT_TYPE_TEXT,
                        'title' => 'If you are an intermediate level learner, I strongly suggest you give the book "Tintenherz" a try',
                        'subreddit' => 'German',
                        'url' => 'https://www.reddit.com/r/German/comments/vlyukg/if_you_are_an_intermediate_level_learner_i/',
                        'createdAt' => '2022-06-27 16:00:42',
                        'authorText' => "I've been reading this book for the past weeks and I'm loving the pace in which I can read it. I feel like it's perfectly suited for B1/B2 level learners (I'd say even A2 learners could read it, albeit in a slower pace).\n\nIt is easy to read but not boringly easy since it can get rather challenging at certain times. Each chapter introduces about 3-5 new useful words, so it's not overwhelming to read as opposed to other more complicated books. The plot is actually entertaining, it has a Harry Potter feel to it, so if this genre interests you then you will like Tintenherz.",
                    ],
                    't1_ia1smh6' => [
                        'originalPostUrl' => 'https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/ia1smh6/',
                        'redditId' => 'uy3sx1',
                        'kind' => Kind::KIND_COMMENT,
                        'contentType' => Type::CONTENT_TYPE_TEXT,
                        'title' => 'Passed my telc B2 exam with a great score (275/300). Super stoked about it!',
                        'subreddit' => 'German',
                        'url' => 'https://www.reddit.com/r/German/comments/uy3sx1/passed_my_telc_b2_exam_with_a_great_score_275300/',
                        'createdAt' => '2022-05-26 09:36:55',
                        'authorText' => 'I’d be glad to offer any advice.',
                        'commentRedditId' => 'ia1smh6',
                    ],
                ]
            ]
        ];
    }
}
