<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\PostAuthorText;
use App\Repository\ContentRepository;
use App\Service\Search\Results;
use App\Service\Typesense\Api;
use Http\Client\Exception;
use Symfony\Contracts\Cache\CacheInterface;
use Typesense\Exceptions\TypesenseClientError;

class Search
{
    const CACHE_KEY_PREFIX = 'search_';

    const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly ContentRepository $contentRepository,
        private readonly Api $searchApi,
        private readonly CacheInterface $cache,
    ) {
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
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function search(?string $searchQuery, array $subreddits = [], array $flairTexts = [], array $tags = [], int $perPage = self::DEFAULT_LIMIT, int $page = 1): Results
    {
        // $cacheKey = $this->generateSearchCacheKey($searchQuery, $subreddits, $flairTexts);

        // return $this->cache->get($cacheKey, function() use ($searchQuery, $subreddits, $flairTexts) {
            $searchRawResults = $this->executeSearch($searchQuery, $subreddits, $flairTexts, $tags, $perPage, $page);
            $searchResults = new Results();
            $searchResults->setPerPage($perPage);
            $searchResults->setPage($searchRawResults['page']);
            $searchResults->setTotal($searchRawResults['found']);

            $contents = [];
            foreach ($searchRawResults['hits'] as $hit) {
                $contentId = (int) $hit['document']['id'];

                $content = $this->contentRepository->find($contentId);
                if ($content instanceof Content) {
                    $contents[] = $content;
                }
            }
            $searchResults->setResults($contents);


            // $contentsNormalized = [];
            // foreach ($contents as $content) {
            //     $contentsNormalized[] = $this->contentNormalizer->normalize($content);
            // }

            return $searchResults;
        // });
    }

    /**
     * Convert the provided Content Entity into a Search Document and Index it.
     *
     * @param  Content  $content
     *
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function indexContent(Content $content)
    {
        $post = $content->getPost();
        $comment = $content->getComment();

        $document = [
            'id'            => (string) $content->getId(),
            'title'  => $post->getTitle(),
            'postRedditId' => $post->getRedditId(),
            'subreddit' => $post->getSubreddit()->getName(),
            'postText' => '',
            'flairText' => '',
            'tags' => [],
            'commentText' => '',
            'createdAt' => (int) $post->getCreatedAt()->format('U'),
        ];

        $latestPostAuthorText = $post->getLatestPostAuthorText();
        if ($latestPostAuthorText instanceof PostAuthorText) {
            $document['postText'] = $latestPostAuthorText->getAuthorText()->getText();
        }

        $flairText = $post->getFlairText();
        if (!empty($flairText)) {
            $document['flairText'] = $flairText;
        }

        if ($comment instanceof Comment) {
            $latestCommentAuthorText = $comment->getLatestCommentAuthorText();
            $document['commentText'] = $latestCommentAuthorText->getAuthorText()->getText();
            $document['createdAt'] = (int) $comment->getLatestCommentAuthorText()->getCreatedAt()->format('U');
        }

        $tags = $content->getTags();
        if ($tags->count() > 0) {
            foreach ($tags as $tag) {
                $document['tags'][] = $tag->getName();
            }
        }

        $response = $this->searchApi->indexDocument($document);
    }

    /**
     * Execute the requested Search against the Search API using the provided
     * parameters.
     *
     * @param  string|null  $searchQuery
     * @param  array  $subreddits
     * @param  array  $flairTexts
     * @param  array  $tags
     * @param  int  $perPage
     * @param  int  $page
     *
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    private function executeSearch(?string $searchQuery, array $subreddits = [], array $flairTexts = [], array $tags = [], int $perPage = self::DEFAULT_LIMIT, int $page = 1): array
    {
        $filters = [];
        if (!empty($subreddits)) {
            $filters[] = sprintf('subreddit:[%s]', implode(',', $subreddits));
        }

        if (!empty($flairTexts)) {
            $filters[] = sprintf('flairText:[%s]', implode(',', $flairTexts));
        }

        if (!empty($tags)) {
            $filters[] = sprintf('tags:[%s]', implode(',', $tags));
        }

        return $this->searchApi->search($searchQuery, $filters, $perPage, $page);
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
