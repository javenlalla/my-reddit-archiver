<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Content;
use App\Repository\SearchContentRepository;
use App\Repository\TagRepository;
use App\Service\Search\Indexer;
use App\Service\Search\Results;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

class Search
{
    const CACHE_KEY_PREFIX = 'search_';

    const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly Indexer $indexer,
        private readonly SearchContentRepository $searchContentRepository,
        private readonly TagRepository $tagRepository,
    ) {
    }

    /**
     * @see Indexer::indexContent()
     *
     * @param  Content  $content
     *
     * @return void
     */
    public function indexContent(Content $content)
    {
        $this->indexer->indexContent($content);
    }

    /**
     * Execute a Search using the provided query and filter parameters.
     *
     * @param  string|null  $searchQuery
     * @param  array  $subreddits  Array of Subreddits to filter results by.
     * @param  array  $flairTexts  Array of Flair Texts to filter results by.
     * @param  array  $tags
     * @param  int  $perPage
     * @param  int  $page
     *
     * @return Results
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function search(?string $searchQuery, array $subreddits = [], array $flairTexts = [], array $tags = [], int $perPage = self::DEFAULT_LIMIT, int $page = 1): Results
    {
        $tagEntities = [];
        if (!empty($tags)) {
            $tagEntities = $this->tagRepository->findByNames($tags);
        }

        return $this->searchContentRepository->search($searchQuery, $subreddits, $flairTexts, $tagEntities, $perPage, $page);
    }

    /**
     * Generate a Cache key specific to the provided Search parameters and
     * filters.
     *
     * @param  string|null  $searchQuery
     * @param  array  $subreddits
     * @param  array  $flairTexts
     *
     * @return string
     */
    private function generateSearchCacheKey(?string $searchQuery, array $subreddits = [], array $flairTexts = []): string
    {
        $key = self::CACHE_KEY_PREFIX;

        if (!empty($searchQuery)) {
            $key .= substr(md5($searchQuery), 0, 6);
        }

        if (!empty($subreddits)) {
            $subredditsString = implode(',', $subreddits);
            $key .= substr(md5($subredditsString), 0, 6);
        }

        if (!empty($flairTexts)) {
            $flairTextsString = implode(',', $flairTexts);
            $key .= substr(md5($flairTextsString), 0, 6);
        }

        return $key;
    }
}
