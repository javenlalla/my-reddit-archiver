<?php
declare(strict_types=1);

namespace App\Service\Typesense;

use App\Service\Typesense\Collection\Contents;
use Http\Client\Exception;
use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

class Api
{
    /** @var Client */
    private Client $client;

    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * Initialize the Typesense Client to communicate with its API.
     *
     * A side effect of this operation is ensuring the expected Collections
     * exist and create as necessary.
     *
     * @return void
     * @throws ConfigError
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function initialize(): void
    {
        $this->client = new Client(
            [
                'api_key' => $this->apiKey,
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

        $this->initializeContentsCollection();
    }

    /**
     * Execute a Search against the Contents Collection using the provided query
     * and filter parameters.
     *
     * @param  string  $searchQuery
     * @param  array  $filters
     *
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function search(string $searchQuery, array $filters = [])
    {
        $filterParam = '';
        if (!empty($filters)) {
            $filterParam = implode('&&', $filters);
        }

        return $this->client->collections['contents']->documents->search([
            'q' => $searchQuery,
            'query_by' => 'title,postText',
            'filter_by' => $filterParam,
        ]);
    }

    /**
     * Retrieve the Contents Collection from the Typesense API.
     *
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function getContentsCollection()
    {
        return $this->client->collections['contents']->retrieve();
    }

    /**
     * Add the following Document to the Search Index for Contents Collection.
     *
     * @param  array  $document
     *
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function indexDocument(array $document): array
    {
        return $this->client->collections['contents']->documents->upsert($document);
    }

    /**
     * Delete indexed Documents by Post Reddit ID.
     *
     * A batch operation is used to capture all Contents Documents associated to
     * the provided Post Reddit ID, such as in cases where multiple Comment
     * Contents under the same Post have been indexed.
     *
     * @param  string  $postRedditId
     *
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function deleteContentByPostRedditId(string $postRedditId): void
    {
        $deleteFilter = sprintf('postRedditId:=%s', $postRedditId);

        $this->client->collections['contents']->documents->delete(['filter_by' => $deleteFilter]);
    }

    /**
     * Create the Contents Collection if it does not already exist.
     *
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    private function initializeContentsCollection(): array
    {
        try {
            $contentsCollection = $this->client->collections['contents']->retrieve();
        } catch (ObjectNotFound $e) {
            $contentsCollection = [];
        } catch (\Exception $e) {
            throw $e;
        }

        if (empty($contentsCollection)) {
            return $this->createCollectionBySchema(Contents::SCHEMA);
        }

        return $contentsCollection;
    }

    /**
     * Create a Collection defined by the provided Schema by calling the
     * Collection Create endpoint on the Typesense API.
     *
     * @param  array  $schema
     *
     * @return array
     * @throws Exception
     * @throws TypesenseClientError
     */
    private function createCollectionBySchema(array $schema): array
    {
        return $this->client->collections->create($schema);
    }
}
