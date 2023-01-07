<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\PostAuthorText;
use App\Normalizer\ContentNormalizer;
use App\Repository\ContentRepository;
use App\Service\Typesense\Api;
use Http\Client\Exception;
use Symfony\Contracts\Cache\CacheInterface;
use Typesense\Exceptions\TypesenseClientError;

class Search
{
    const CACHE_KEY_PREFIX = 'search_';

    public function __construct(
        private readonly ContentRepository $contentRepository,
        private readonly ContentNormalizer $contentNormalizer,
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
     *
     * @return array
     */
    public function search(?string $searchQuery, array $subreddits = [], array $flairTexts = []): array
    {
        $cacheKey = $this->generateSearchCacheKey($searchQuery, $subreddits, $flairTexts);

        return $this->cache->get($cacheKey, function() use ($searchQuery, $subreddits, $flairTexts) {
            $contents = [];
            $searchResults = $this->executeSearch($searchQuery, $subreddits, $flairTexts);
            foreach ($searchResults['hits'] as $hit) {
                $contentId = (int) $hit['document']['id'];

                $content = $this->contentRepository->find($contentId);
                if ($content instanceof Content) {
                    $contents[] = $content;
                }
            }

            $contentsNormalized = [];
            foreach ($contents as $content) {
                $contentsNormalized[] = $this->contentNormalizer->normalize($content);
            }

            return $contentsNormalized;
        });
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
            'subreddit' => $post->getSubreddit(),
            'postText' => '',
            'flairText' => '',
            'commentText' => '',
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
     *
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    private function executeSearch(?string $searchQuery, array $subreddits = [], array $flairTexts = []): array
    {
        $filters = [];
        if (!empty($subreddits)) {
            $filters[] = sprintf('subreddit:[%s]', implode(',', $subreddits));
        }

        if (!empty($flairTexts)) {
            $filters[] = sprintf('flairText:[%s]', implode(',', $flairTexts));
        }

        return $this->searchApi->search($searchQuery, $filters);
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
