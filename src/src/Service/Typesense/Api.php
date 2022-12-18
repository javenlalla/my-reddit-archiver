<?php
declare(strict_types=1);

namespace App\Service\Typesense;

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
     * @return void
     * @throws ConfigError
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

        // @TODO: Move this to a start-up Command.
        $this->initializeContentsCollection();
    }

    public function getKeys()
    {
        return $this->client->keys->retrieve();
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
     * Create the Contents Collection if it does not already exist.
     *
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    private function initializeContentsCollection()
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

            $this->client->collections->create($schema);
        }
    }
}
