<?php

namespace App\Service\Reddit;

use App\Entity\Post;
use App\Repository\ApiUserRepository;
use Exception;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @property HttpClientInterface $client
 */
class Api
{
    const OAUTH_ENDPOINT = 'https://www.reddit.com/api/v1/access_token';

    const POST_DETAIL_ENDPOINT = 'https://oauth.reddit.com/api/info?id=%s';

    const SAVED_POSTS_ENDPOINT = 'https://oauth.reddit.com/user/%s/saved';

    const METHOD_GET = 'GET';

    const METHOD_POST = 'POST';

    private string $accessToken;

    private string $userAgent;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ApiUserRepository $apiUserRepository,
        private readonly string $username,
        private readonly string $password,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
        $this->accessToken = $this->getAccessToken();
        $this->setUserAgent();
    }

    /**
     * Retrieve a Post from the API by its type and ID.
     *
     * @param  string  $type
     * @param  string  $id
     *
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getPostByRedditId(string $type, string $id): array
    {
        return $this->getPostByFullRedditId(sprintf(Post::FULL_REDDIT_ID_FORMAT, $type, $id));
    }

    /**
     * Retrieve a Post from the API by its Reddit "fullName" ID.
     * Example: t1_vlyukg
     *
     * @param  string  $fullRedditId
     *
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getPostByFullRedditId(string $fullRedditId): array
    {
        $endpoint = sprintf(self::POST_DETAIL_ENDPOINT, $fullRedditId);
        $response = $this->executeCall(self::METHOD_GET, $endpoint);

        return $response->toArray();
    }

    public function getSavedPosts(): array
    {
        return $this->client
            ->request('GET', $this->getSavedPostsUrl() . '?limit=1',
                [
                    'auth_bearer' => $this->accessToken,
                    'headers' => [
                        'User-Agent' => $this->userAgent,
                    ]
                ]
            )
            ->toArray();
    }

    public function getPostComments()
    {
        $comments = $this->client
            ->request('GET', 'https://oauth.reddit.com/comments/vlyukg?raw_json=1',
                [
                    'auth_bearer' => $this->accessToken,
                    'headers' => [
                        'User-Agent' => $this->userAgent,
                    ]
                ]
            )
            ->toArray();

        $opComment = $comments[0];
        $comments = new Comments($comments[1]['data']['children']);

        return $comments->toJson();
    }

    /**
     * Retrieve Comments under a Post by the Post's Reddit ID.
     *
     * @param  string  $redditId
     *
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getPostCommentsByRedditId(string $redditId): array
    {
        $commentsUrl = sprintf('https://oauth.reddit.com/comments/%s?raw_json=1', $redditId);
        $response = $this->executeCall(self::METHOD_GET, $commentsUrl);

        return $response->toArray();

        // $opComment = $comments[0];
        // $comments = new Comments($comments[1]['data']['children']);
        //
        // return $comments->toJson();
    }

    private function executeCall(string $method, string $endpoint, array $options = [], bool $retry = false): ResponseInterface
    {
        $options['auth_bearer'] = $this->accessToken;
        if (empty($options['headers'])) {
            $options['headers'] = [];
        }

        $options['headers']['User-Agent'] = $this->userAgent;

        $response = $this->client->request($method, $endpoint, $options);
        if ($response->getStatusCode() === 401 && $retry === false) {
            $this->refreshToken();
            return $this->executeCall($method, $endpoint, $options, true);
        } else if ($response->getStatusCode() === 401 && $retry === true) {
            throw new Exception(sprintf('Unable to execute authenticated call to %s', $endpoint));
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception(sprintf(
                'API call failed. Status Code: %d. Endpoint: %s. Options: %s',
                $response->getStatusCode(),
                $endpoint,
                var_export($options, true)
            ));
        }

        return $response;
    }

    private function getSavedPostsUrl(): string
    {
        return sprintf(self::SAVED_POSTS_ENDPOINT, $this->username);
    }

    private function getAccessToken()
    {
        if (empty($this->accessToken)) {
            $accessToken = $this->apiUserRepository->getAccessTokenByUsername($this->username);
            if (empty($accessToken)) {
                $this->refreshToken();
            }
        }

        return $this->accessToken;
    }

    private function refreshToken()
    {
        if (empty($this->userAgent)) {
            $this->setUserAgent();
        }

        $options = [
            'auth_basic' => $this->clientId.':'.$this->clientSecret,
            'headers' => [
                'User-Agent' => $this->userAgent,
            ],
            'body' => [
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password,
            ]
        ];

        $response = $this->client->request(self::METHOD_POST, self::OAUTH_ENDPOINT, $options);
        if ($response->getStatusCode() === 200) {
            $responseData = $response->toArray();
            $this->accessToken = $responseData['access_token'];
            $this->apiUserRepository->saveToken($this->username, $this->accessToken);

            return;
        }

        throw new Exception(sprintf('Unable to retrieve Access Token: %s', var_export($response->toArray(), true)));
    }

    /**
     * Set a unique User Agent for API requests using the current Username.
     *
     * @return void
     */
    private function setUserAgent()
    {
        $this->userAgent = sprintf('User-Agent from %s', $this->username);
    }
}
