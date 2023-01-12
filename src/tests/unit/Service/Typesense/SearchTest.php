<?php
declare(strict_types=1);

namespace App\Tests\unit\Service\Typesense;

use App\Entity\Tag;
use App\Repository\ContentRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Service\Search;
use App\Service\Typesense\Collection\Contents;
use Http\Client\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;
use Typesense\Exceptions\TypesenseClientError;

class SearchTest extends KernelTestCase
{
    private Search $searchService;

    private PostRepository $postRepository;

    private TagRepository $tagRepository;

    private ContentRepository $contentRepository;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->searchService = $container->get(Search::class);
        $this->postRepository = $container->get(PostRepository::class);
        $this->tagRepository = $container->get(TagRepository::class);
        $this->contentRepository = $container->get(ContentRepository::class);

        $this->cleanupDocuments();
    }

    /**
     * Execute basic search queries and verify the excepted results are surfaced
     * for each query.
     *
     * @dataProvider basicSearchQueriesDataProvider()
     *
     * @param  string  $postRedditId
     * @param  string  $searchQuery
     *
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function testBasicSearchQueries(string $postRedditId, string $searchQuery): void
    {
        $searchResults = $this->searchService->search($searchQuery);
        $this->assertIsArray($searchResults);
        $this->assertCount(0, $searchResults);

        $post = $this->postRepository->findOneBy(['redditId' => $postRedditId]);
        $content = $post->getContent();
        $this->searchService->indexContent($content);

        $searchResults = $this->searchService->search($searchQuery);
        $this->assertCount(1, $searchResults);
    }

    /**
     * Verify Search results can be filtered by Subreddit.
     *
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function testSearchWithSubredditFilter()
    {
        $firstPostRedditId = 'x00002';
        $secondPostRedditId = 'x00003';
        $searchQuery = 'disclosure';

        foreach ([$firstPostRedditId, $secondPostRedditId] as $postRedditId) {
            $post = $this->postRepository->findOneBy(['redditId' => $postRedditId]);
            $content = $post->getContent();
            $this->searchService->indexContent($content);
        }

        $searchResults = $this->searchService->search($searchQuery);
        $this->assertCount(2, $searchResults);

        // Verify filtering by one Subreddit.
        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            subreddits: ['jokesALT'] // Intentionally use different cases to verify results still surface.
        );
        $this->assertCount(1, $searchResults);

        // Verify filtering by multiple Subreddits.
        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            subreddits: ['jokesALT, JOKES'] // Intentionally use different cases to verify results still surface.
        );
        $this->assertCount(2, $searchResults);
    }

    /**
     * Verify Search results can be filtered by Flair (Link Posts only, not
     * Comments).
     *
     * @return void
     */
    public function testSearchWithFlairFilter()
    {
        $searchQuery = 'disclosure';
        $this->indexContents([
            ['kind' => 'post', 'redditId' => 'x00002'],
            ['kind' => 'post', 'redditId' => 'x00003'],
            ['kind' => 'post', 'redditId' => 'x00004'],
        ]);

        $searchResults = $this->searchService->search($searchQuery);
        $this->assertCount(3, $searchResults);

        // Verify filtering by one Flair Text.
        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            flairTexts: ['GrEAT Dad joke'] // Intentionally use different cases to verify results still surface.
        );
        $this->assertCount(1, $searchResults);

        // Verify no results filtering by non-existent Flair Texts.
        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            flairTexts: ['JokesJokes'] // Intentionally use different cases to verify results still surface.
        );
        $this->assertCount(0, $searchResults);
    }

    /**
     * Verify Search results can be filtered by Tags.
     *
     * @return void
     */
    public function testSearchWithTags()
    {
        $searchQuery = 'disclosure';
        $this->indexContents([
            ['kind' => 'post', 'redditId' => 'x00002'],
            ['kind' => 'post', 'redditId' => 'x00003'],
            ['kind' => 'post', 'redditId' => 'x00004'],
        ]);

        $funnyTag = new Tag();
        $funnyTag->setName('funny');
        $this->tagRepository->add($funnyTag, true);

        $hilariousTag = new Tag();
        $hilariousTag->setName('HilarioUS');
        $this->tagRepository->add($hilariousTag, true);

        $seriousTag = new Tag();
        $seriousTag->setName('Really SERious');
        $this->tagRepository->add($seriousTag, true);

        $post = $this->postRepository->findOneBy(['redditId' => 'x00002']);
        $content = $post->getContent();
        $content->addTag($funnyTag);
        $this->contentRepository->add($content, true);

        $post = $this->postRepository->findOneBy(['redditId' => 'x00003']);
        $content = $post->getContent();
        $content->addTag($funnyTag);
        $content->addTag($hilariousTag);
        $this->contentRepository->add($content, true);

        $post = $this->postRepository->findOneBy(['redditId' => 'x00004']);
        $content = $post->getContent();
        $content->addTag($funnyTag);
        $content->addTag($hilariousTag);
        $content->addTag($seriousTag);
        $this->contentRepository->add($content, true);

        $searchResults = $this->searchService->search($searchQuery);
        $this->assertCount(3, $searchResults);

        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            tags: ['hilarious', 'SERIOUS'] // Intentionally use different cases to verify results still surface.
        );
        $this->assertCount(2, $searchResults);

        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            tags: ['funny']
        );
        $this->assertCount(3, $searchResults);

        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            tags: ['serIOUS']
        );
        $this->assertCount(1, $searchResults);

        // Verify no results filtering by non-existent Tags.
        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            tags: ['Not Funny']
        );
        $this->assertCount(0, $searchResults);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Index the provided Contents.
     *
     * @param  array  $contentsData
     *
     * @return void
     */
    private function indexContents(array $contentsData): void
    {
        foreach ($contentsData as $contentData) {
            if ($contentData['kind'] === 'post') {
                $post = $this->postRepository->findOneBy(['redditId' => $contentData['redditId']]);
                $content = $post->getContent();
                $this->searchService->indexContent($content);
            } else {
                // @TODO: Add logic for Comments.
            }
        }
    }

    /**
     * Remove Documents from the Search Index that are targeted for these
     * Tests.
     *
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    private function cleanupDocuments(): void
    {
        $container = static::getContainer();
        $apiKey = $container->getParameter('app.typesense.api_key');

        $client = new Client(
            [
                'api_key' => $apiKey,
                'nodes' => [
                    [
                        'host' => 'localhost',
                        'port' => '8108',
                        'protocol' => 'http',
                    ],
                ],
                'client' => new HttplugClient(),
            ]
        );

        $client->collections['contents']->delete();
        $client->collections->create(Contents::SCHEMA);
    }

    /**
     * Provide Contents data for Search queries testing.
     *
     * @return array[]
     */
    public function basicSearchQueriesDataProvider()
    {
        return [
            'Post Title' => [
                'postRedditId' => 'x00001',
                'searchQuery' => 'vegeta',
            ],
            'Post Author Text' => [
                'postRedditId' => 'x00002',
                'searchQuery' => 'disclosure',
            ],
            'Comment Author Text' => [
                'postRedditId' => 'x00005',
                'searchQuery' => 'washing',
            ],
        ];
    }
}
