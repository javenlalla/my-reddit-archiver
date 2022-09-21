<?php

namespace App\Service\Reddit;

use App\Entity\Post;
use App\Event\RedditApiCallEvent;
use App\Repository\ApiUserRepository;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
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

    const MORE_CHILDREN_ENDPOINT = 'https://oauth.reddit.com/api/morechildren';

    const METHOD_GET = 'GET';

    const METHOD_POST = 'POST';

    private string $accessToken;

    private string $userAgent;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ApiUserRepository $apiUserRepository,
        private readonly CacheInterface $cachePoolRedis,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $username,
        private readonly string $password,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
        $this->setUserAgent();
    }

    /**
     * Retrieve a Post from the API by its type and ID.
     *
     * @param  string  $type
     * @param  string  $id
     *
     * @return array
     * @throws InvalidArgumentException
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
     * @throws InvalidArgumentException
     */
    public function getPostByFullRedditId(string $fullRedditId): array
    {
        $cacheKey = md5('post-'.$fullRedditId);

        return $this->cachePoolRedis->get($cacheKey, function() use ($fullRedditId) {
            $endpoint = sprintf(self::POST_DETAIL_ENDPOINT, $fullRedditId);
            $response = $this->executeCall(self::METHOD_GET, $endpoint);

            return $response->toArray();
        });
    }

    /**
     * Retrieve the Saved Posts under the current user's (as configured in the
     * application) Reddit Profile.
     *
     * @param  int  $limit
     * @param  string  $after
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getSavedPosts(int $limit = 100, string $after = ''): array
    {
        $endpoint = sprintf(self::SAVED_POSTS_ENDPOINT, $this->username);
        $endpoint = $endpoint . sprintf('?limit=%d', $limit);
        if (!empty($after)) {
            $endpoint = $endpoint . sprintf('&after=%s', $after);
        }

        $cacheKey = md5($endpoint);

        return $this->cachePoolRedis->get($cacheKey, function(ItemInterface $item) use ($endpoint) {
            $response = $this->executeCall(self::METHOD_GET, $endpoint)->toArray();

            return $response['data'];
        });
    }

    /**
     * Retrieve Comments under a Post by the Post's Reddit ID.
     *
     * @param  string  $redditId
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getPostCommentsByRedditId(string $redditId): array
    {
        $cacheKey = md5('comments-'.$redditId);

        return $this->cachePoolRedis->get($cacheKey, function() use ($redditId) {
            $commentsUrl = sprintf('https://oauth.reddit.com/comments/%s?raw_json=1', $redditId);
            $response = $this->executeCall(self::METHOD_GET, $commentsUrl);

            return $response->toArray();
        });
    }

    /**
     * Retrieve the "more" Comments data under the specified Reddit ID using
     * the provided Children data.
     *
     * @param  string  $postRedditId
     * @param  array  $moreChildrenData
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getMoreChildren(string $postRedditId, array $moreChildrenData): array
    {
        $body = [
            'link_id' => sprintf('t3_%s', $postRedditId),
            'children' => implode(',', $moreChildrenData['children']),
            'api_type' => 'json',
            'limit_children' => false,
        ];

        $cacheKey = md5('more-children-'. $postRedditId . '-' . $body['children']);

        return $this->cachePoolRedis->get($cacheKey, function() use ($body) {
            $response = $this->executeCall(self::METHOD_POST, self::MORE_CHILDREN_ENDPOINT, ['body' => $body]);

            return $response->toArray();
        });
    }

    /**
     * Retrieve the JSON response data for the provided Post URL using its
     * `.json` equivalent endpoint.
     *
     * @param  string  $postLink
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getPostFromJsonUrl(string $postLink): array
    {
        $jsonUrl = $this->sanitizePostLinkToJsonFormat($postLink);
        $cacheKey = md5('link-'.$jsonUrl);

        return $this->cachePoolRedis->get($cacheKey, function() use ($jsonUrl) {
            return
                $this->executeSimpleCall(self::METHOD_GET, $jsonUrl)
                ->toArray();
        });
    }

    /**
     * Core function which executes a call to the Reddit API.
     *
     * @param  string  $method
     * @param  string  $endpoint
     * @param  array  $options
     * @param  bool  $retry
     *
     * @return ResponseInterface
     * @throws TransportExceptionInterface
     */
    private function executeCall(string $method, string $endpoint, array $options = [], bool $retry = false): ResponseInterface
    {
        if (isset($this->accessToken)) {
            $options['auth_bearer'] = $this->accessToken;
        } else {
            $options['auth_bearer'] = $this->getAccessToken();
        }

        if (empty($options['headers'])) {
            $options['headers'] = [];
        }

        $options['headers']['User-Agent'] = $this->userAgent;

        $response = $this->client->request($method, $endpoint, $options);
        $this->eventDispatcher->dispatch(new RedditApiCallEvent($this->username), RedditApiCallEvent::NAME);

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

    /**
     * @TODO: Investigate if this can be combined with `executeCall` function.
     *
     * Execute an HTTP request to the targeted endpoint.
     *
     * No auth or retry functionality is included in this logic.
     *
     * @param  string  $method
     * @param  string  $endpoint
     * @param  array  $options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function executeSimpleCall(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        if (empty($options['headers'])) {
            $options['headers'] = [];
        }

        $options['headers']['User-Agent'] = $this->userAgent;

        $response = $this->client->request($method, $endpoint, $options);
        // $this->eventDispatcher->dispatch(new RedditApiCallEvent($this->username), RedditApiCallEvent::NAME);

        // if ($response->getStatusCode() === 401 && $retry === false) {
        //     $this->refreshToken();
        //     return $this->executeCall($method, $endpoint, $options, true);
        // } else if ($response->getStatusCode() === 401 && $retry === true) {
        //     throw new Exception(sprintf('Unable to execute authenticated call to %s', $endpoint));
        // }

        if ($response->getStatusCode() !== 200) {
            throw new Exception(sprintf(
                'API call failed. Response: %s. Status Code: %d. Endpoint: %s. Options: %s',
                var_export($response->toArray(), true),
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
        if (!isset($this->accessToken) || empty($this->accessToken)) {
            $this->accessToken = $this->apiUserRepository->getAccessTokenByUsername($this->username);

            if (empty($this->accessToken)) {
                return $this->refreshToken();
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
        $this->eventDispatcher->dispatch(new RedditApiCallEvent($this->username), RedditApiCallEvent::NAME);

        if ($response->getStatusCode() === 200) {
            $responseData = $response->toArray();
            $this->accessToken = $responseData['access_token'];
            $this->apiUserRepository->saveToken($this->username, $this->accessToken);

            return $this->accessToken;
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

    /**
     * Analyze the provided Post link and convert it to its JSON-equivalent
     * representation.
     *
     * @param  string  $postLink
     *
     * @return string
     */
    private function sanitizePostLinkToJsonFormat(string $postLink): string
    {
        // Remove trailing slash, if any.
        $sanitizedPostLink = rtrim($postLink, '/');

        // Append .json to initiate JSON response.
        $sanitizedPostLink .= '.json';

        return $sanitizedPostLink;
    }
}
