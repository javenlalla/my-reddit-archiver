<?php
declare(strict_types=1);

namespace App\Tests\Service\Typesense;

use App\Repository\PostRepository;
use App\Service\Typesense\Api;
use App\Service\Typesense\Search;
use Http\Client\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Typesense\Exceptions\TypesenseClientError;

class SearchTest extends KernelTestCase
{
    private Search $searchService;

    private Api $typesenseApi;

    private PostRepository $postRepository;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->searchService = $container->get(Search::class);
        $this->typesenseApi = $container->get(Api::class);
        $this->postRepository = $container->get(PostRepository::class);

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
        $this->assertCount(0, $searchResults['hits']);

        $post = $this->postRepository->findOneBy(['redditId' => $postRedditId]);
        $content = $post->getContent();
        $this->searchService->indexContent($content);

        $searchResults = $this->searchService->search($searchQuery);
        $this->assertCount(1, $searchResults['hits']);
    }

    public function tearDown(): void
    {
        parent::tearDown();
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
        $targetPostRedditIds = [
            'x00001',
            'x00002',
        ];

        foreach ($targetPostRedditIds as $targetPostRedditId) {
            $this->typesenseApi->deleteContentByPostRedditId($targetPostRedditId);
        }
    }

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
        ];
    }
}