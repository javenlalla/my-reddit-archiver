<?php
declare(strict_types=1);

namespace App\Service\Typesense;

use App\Entity\Content;
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
     * Add the following Content to the Search Index for Contents Collection.
     *
     * @param  Content  $content
     *
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function indexContent(Content $content)
    {
        $document = [
            'id'            => (string) $content->getId(),
            'title'  => $content->getPost()->getTitle(),
        ];

        $response = $this->client->collections['contents']->documents->upsert($document);
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
            $schema = [
                'name'      => 'contents',
                'fields'    => [
                    [
                        'name'  => 'title',
                        'type'  => 'string'
                    ],
                ],
            ];

            return $this->createCollectBySchema($schema);
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
    private function createCollectBySchema(array $schema): array
    {
        return $this->client->collections->create($schema);
    }
}
