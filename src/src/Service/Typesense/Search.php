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
     *
     * @return array
     */
    public function search(string $searchQuery): array
    {
        return $this->typesenseApi->search($searchQuery);
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
            'postText' => '',
        ];

        $latestPostAuthorText = $post->getLatestPostAuthorText();
        if ($latestPostAuthorText instanceof PostAuthorText) {
            $document['postText'] = $latestPostAuthorText->getAuthorText()->getText();
        }

        $response = $this->typesenseApi->indexDocument($document);
    }
}
