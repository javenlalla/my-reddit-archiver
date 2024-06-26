<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Entity\Content;
use App\Entity\SearchContent;
use App\Entity\Tag;
use App\Repository\ContentRepository;
use App\Repository\PostRepository;
use App\Repository\SearchContentRepository;
use App\Repository\TagRepository;
use App\Service\Search;
use App\Service\Search\Results;
use Doctrine\ORM\EntityManagerInterface;
use Http\Client\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SearchTest extends KernelTestCase
{
    private Search $searchService;

    private PostRepository $postRepository;

    private TagRepository $tagRepository;

    private ContentRepository $contentRepository;

    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->searchService = $container->get(Search::class);
        $this->postRepository = $container->get(PostRepository::class);
        $this->tagRepository = $container->get(TagRepository::class);
        $this->contentRepository = $container->get(ContentRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $this->cleanupDocuments();
    }

    /**
     * Execute basic search queries and verify the excepted results are surfaced
     * for each query.
     *
     * @group ci-tests
     *
     * @dataProvider basicSearchQueriesDataProvider()
     *
     * @param  string  $postRedditId
     * @param  string  $searchQuery
     *
     * @return void
     * @throws Exception
     */
    public function testBasicSearchQueries(string $postRedditId, string $searchQuery): void
    {
        $searchResults = $this->searchService->search($searchQuery);
        $this->assertInstanceOf(Results::class, $searchResults);
        $this->assertEquals(0, $searchResults->getTotal());

        $post = $this->postRepository->findOneBy(['redditId' => $postRedditId]);
        $content = $post->getContent();
        $this->searchService->indexContent($content);

        $searchResults = $this->searchService->search($searchQuery);
        $this->assertEquals(Search::DEFAULT_LIMIT, $searchResults->getPerPage());
        $this->assertEquals(1, $searchResults->getTotal());
        $this->assertInstanceOf(SearchContent::class, $searchResults->getResults()[0]);
    }

    /**
     * Verify Search results can be sorted by their Post's creation date.
     *
     * @return void
     * @throws Exception
     */
    public function testSearchWithSort()
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
        $this->assertEquals(2, $searchResults->getTotal());

        $this->assertEquals($secondPostRedditId, $searchResults->getResults()[0]->getPost()->getRedditId());
    }

    /**
     * Verify Search results can be filtered by Subreddit.
     *
     * @return void
     * @throws Exception
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
        $this->assertEquals(2, $searchResults->getTotal());

        // Verify filtering by one Subreddit.
        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            subreddits: ['JokesAlt']
        );
        $this->assertEquals(1, $searchResults->getTotal());

        // Verify filtering by multiple Subreddits.
        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            subreddits: ['JokesAlt', 'Jokes']
        );
        $this->assertEquals(2, $searchResults->getTotal());
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
        $this->assertEquals(3, $searchResults->getTotal());

        // Verify pagination.
        $searchResults = $this->searchService->search($searchQuery, perPage: 1, page: 2);
        $this->assertEquals(3, $searchResults->getTotal());
        $this->assertEquals(1, $searchResults->getCount());

        // Verify filtering by one Flair Text.
        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            flairTexts: ['Great Dad Joke']
        );
        $this->assertEquals(1, $searchResults->getTotal());

        // Verify no results filtering by non-existent Flair Texts.
        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            flairTexts: ['Jokes']
        );
        $this->assertEquals(0, $searchResults->getTotal());
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
        $hilariousTag->setName('hilarious');
        $this->tagRepository->add($hilariousTag, true);

        $seriousTag = new Tag();
        $seriousTag->setName('Really Serious');
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
        $this->assertEquals(3, $searchResults->getTotal());

        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            tags: ['hilarious', 'Serious']
        );
        $this->assertEquals(2, $searchResults->getTotal());

        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            tags: ['funny']
        );
        $this->assertEquals(3, $searchResults->getTotal());

        $searchResults = $this->searchService->search(
            searchQuery: $searchQuery,
            tags: ['Really Serious']
        );
        $this->assertEquals(1, $searchResults->getTotal());
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
     * Remove Entities from the Search Contents that are targeted for these
     * Tests.
     *
     * @return void
     */
    private function cleanupDocuments(): void
    {
        $stmt = $this->entityManager->getConnection()->prepare('DELETE FROM search_content;');
        $stmt->executeStatement();
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
