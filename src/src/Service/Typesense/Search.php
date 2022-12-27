<?php
declare(strict_types=1);

namespace App\Service\Typesense;

use App\Entity\Content;
use App\Entity\PostAuthorText;
use Http\Client\Exception;
use Typesense\Exceptions\TypesenseClientError;

class Search
{
    public function __construct(private readonly Api $typesenseApi)
    {
    }

    /**
     * Execute a Search using the provided query and filter parameters.
     *
     * @param  string  $searchQuery
     * @param  array  $subreddits  Array of Subreddits to filter results by.
     * @param  array  $flairTexts  Array of Flair Texts to filter results by.
     *
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function search(string $searchQuery, array $subreddits = [], array $flairTexts = []): array
    {
        $filters = [];
        if (!empty($subreddits)) {
            $filters[] = sprintf('subreddit:[%s]', implode(',', $subreddits));
        }

        if (!empty($flairTexts)) {
            $filters[] = sprintf('flairText:[%s]', implode(',', $flairTexts));
        }

        return $this->typesenseApi->search($searchQuery, $filters);
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

        $document = [
            'id'            => (string) $content->getId(),
            'title'  => $post->getTitle(),
            'postRedditId' => $post->getRedditId(),
            'subreddit' => $post->getSubreddit(),
            'postText' => '',
            'flairText' => '',
        ];

        $latestPostAuthorText = $post->getLatestPostAuthorText();
        if ($latestPostAuthorText instanceof PostAuthorText) {
            $document['postText'] = $latestPostAuthorText->getAuthorText()->getText();
        }

        $flairText = $post->getFlairText();
        if (!empty($flairText)) {
            $document['flairText'] = $flairText;
        }

        $response = $this->typesenseApi->indexDocument($document);
    }
}
