<?php
declare(strict_types=1);

namespace App\Service\Reddit;

use App\Entity\Comment;
use App\Entity\ProfileContentGroup;
use App\Event\RedditApiCallEvent;
use App\Repository\ApiUserRepository;
use App\Service\Reddit\Api\Context;
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

    const POST_DETAIL_ENDPOINT = 'https://www.reddit.com/api/info/.json?id=%s';

    const POST_COMMENTS_ENDPOINT = 'https://oauth.reddit.com/comments/%s/?raw_json=1';

    const UNAUTHENTICATED_POST_COMMENTS_ENDPOINT = 'https://www.reddit.com/comments/%s/.json?raw_json=1';

    const SAVED_POSTS_ENDPOINT = 'https://oauth.reddit.com/user/%s/saved';

    const PROFILE_GROUP_ENDPOINTS = [
        ProfileContentGroup::PROFILE_GROUP_SAVED => 'https://oauth.reddit.com/user/%s/saved',
        ProfileContentGroup::PROFILE_GROUP_COMMENTS => 'https://oauth.reddit.com/user/%s/comments',
        ProfileContentGroup::PROFILE_GROUP_UPVOTED => 'https://oauth.reddit.com/user/%s/upvoted',
        ProfileContentGroup::PROFILE_GROUP_DOWNVOTED => 'https://oauth.reddit.com/user/%s/downvoted',
        ProfileContentGroup::PROFILE_GROUP_SUBMITTED => 'https://oauth.reddit.com/user/%s/submitted',
    ];

    const MORE_CHILDREN_ENDPOINT = 'https://oauth.reddit.com/api/morechildren/';

    const UNAUTHENTICATED_MORE_CHILDREN_ENDPOINT = 'https://www.reddit.com/api/morechildren/.json?api_type=json&limit_children=false&link_id=t3_%s&children=%s';

    const INFO_ENDPOINT = 'https://oauth.reddit.com/api/info?id=%s';

    const UNAUTHENTICATED_INFO_ENDPOINT = 'https://www.reddit.com/api/info/.json?id=%s';

    const METHOD_GET = 'GET';

    const METHOD_POST = 'POST';

    const MORE_CHILDREN_BATCH_SIZE = 750;

    const INFO_BATCH_SIZE = 100;

    const COMMENTS_SORT_NEW = 'new';

    const HOT_CONTENTS_JSON_URL = 'https://oauth.reddit.com/hot/?limit=%d';

    const UNAUTHENTICATED_HOT_CONTENTS_JSON_URL = 'https://www.reddit.com/hot/.json?limit=%d';

    const DEFAULT_HOT_LIMIT = 10;

    private string $accessToken;

    private string $userAgent;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ApiUserRepository $apiUserRepository,
        private readonly CacheInterface $appCachePool,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $username,
        private readonly string $password,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
        $this->setUserAgent();
    }

    /**
     * Retrieve the Saved Posts under the current user's (as configured in the
     * application) Reddit Profile.
     *
     * @param  Context  $context
     * @param  int  $limit
     * @param  string  $after
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getSavedContents(Context $context, int $limit = 100, string $after = ''): array
    {
        $endpoint = sprintf(self::SAVED_POSTS_ENDPOINT, $this->username);
        $endpoint = $endpoint . sprintf('?limit=%d', $limit);
        if (!empty($after)) {
            $endpoint = $endpoint . sprintf('&after=%s', $after);
        }

        $cacheKey = md5($endpoint);

        return $this->appCachePool->get($cacheKey, function(ItemInterface $item) use ($context, $endpoint) {
            $response = $this->executeCall($context, self::METHOD_GET, $endpoint)->toArray();

            return $response['data'];
        });
    }

    /**
     * Retrieve the Contents under the specified group in the user's profile.
     *
     * @param  Context  $context
     * @param  string  $profileGroup
     * @param  int  $limit
     * @param  string  $after
     *
     * @return array
     */
    public function getContentsByProfileGroup(Context $context, string $profileGroup, int $limit = 100, string $after = ''): array
    {
        if (!isset(self::PROFILE_GROUP_ENDPOINTS[$profileGroup])) {
            return [];
        }

        $endpointBase = self::PROFILE_GROUP_ENDPOINTS[$profileGroup];

        $endpoint = sprintf($endpointBase, $this->username);
        $endpoint = $endpoint . sprintf('?limit=%d', $limit);
        if (!empty($after)) {
            $endpoint = $endpoint . sprintf('&after=%s', $after);
        }

        $response = $this->executeCall($context, self::METHOD_GET, $endpoint)->toArray();

        return $response['data'];
    }

    /**
     * Retrieve Comments under a Post by the Post's Reddit ID.
     *
     * @param  Context  $context
     * @param  string  $redditId
     * @param  string  $sort
     * @param  int  $limit
     * @param  Comment|null  $byComment  Provide Comment Entity to only get Comments (replies) under this targeted Comment.
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getPostCommentsByRedditId(Context $context, string $redditId, string $sort = '', int $limit = -1, Comment $byComment = null): array
    {
        $cacheKeyData = 'comments-'.$redditId.'-'.$sort.'-'.$limit;
        if ($byComment instanceof Comment) {
            $cacheKeyData .= '-' . $byComment->getRedditId();
        }

        $cacheKey = md5($cacheKeyData);
        return $this->appCachePool->get($cacheKey, function() use ($context, $redditId, $sort, $limit, $byComment) {
            $commentsUrl = sprintf(self::POST_COMMENTS_ENDPOINT, $redditId);
            if ($byComment instanceof Comment) {
                $commentsUrl .= '&comment=' . $byComment->getRedditId();
            }

            if (!empty($sort)) {
                $commentsUrl .= '&sort=' . $sort;
            }

            if ($limit > 0) {
                $commentsUrl .= '&limit=' . $limit;
            }

            $response = $this->executeCall($context, self::METHOD_GET, $commentsUrl);
            $responseData = $response->toArray();

            if (!empty($responseData[1]['data']['children'])) {
                return $responseData[1]['data']['children'];
            }

            return [];
        });
    }

    /**
     * Retrieve the "more" Comments data under the specified Reddit ID using
     * the provided Children data.
     *
     * The More Children data is batched in order to avoid a `414` error
     * returned from Reddit due to the URI being too long.
     *
     * @param  Context  $context
     * @param  string  $postRedditId
     * @param  array  $moreChildrenData
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getMoreChildren(Context $context, string $postRedditId, array $moreChildrenData): array
    {
        $childrenDataGroups = array_chunk($moreChildrenData['children'], self::MORE_CHILDREN_BATCH_SIZE);
        $allRetrievedChildren = [];

        foreach ($childrenDataGroups as $childrenData) {
            $body = [
                'api_type' => 'json',
                'limit_children' => false,
                'link_id' => 't3_' . $postRedditId,
                'children' => implode(',', $childrenData),
            ];

            $cacheKey = md5('more-children-'. implode(',', $body));

            $retrievedChildren = $this->appCachePool->get($cacheKey, function() use ($context, $body) {
                $options = [
                    'body' => $body,
                ];

                $response = $this->executeCall($context, self::METHOD_POST, self::MORE_CHILDREN_ENDPOINT, $options);

                return $response->toArray();
            });

            $allRetrievedChildren = [...$allRetrievedChildren, ...$retrievedChildren['json']['data']['things']];
        }

        return $allRetrievedChildren;
    }

    /**
     * Retrieve the JSON response data for the provided Post URL using its
     * `.json` equivalent endpoint.
     *
     * @param  Context  $context
     * @param  string  $postLink
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getPostFromJsonUrl(Context $context, string $postLink): array
    {
        $jsonUrl = $this->sanitizePostLinkToJsonFormat($postLink);
        $cacheKey = md5('link-'.$jsonUrl);

        return $this->appCachePool->get($cacheKey, function() use ($context, $jsonUrl) {
            return
                $this->executeCall($context, self::METHOD_GET, $jsonUrl)
                ->toArray();
        });
    }

    /**
     * Retrieve the JSON response for the currently trending Hot Posts.
     *
     * @param  Context  $context
     * @param  int  $limit
     *
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getHotPosts(Context $context,int $limit = self::DEFAULT_HOT_LIMIT): array
    {
        $url = sprintf(self::HOT_CONTENTS_JSON_URL, $limit);

        return
            $this->executeCall($context,self::METHOD_GET, $url)
                ->toArray();
    }

    /**
     * This is a shortcut function to retrieve the details for a single Reddit
     * item (Post, Comment, Subreddit, etc.) by its Reddit ID.
     *
     * Because this is a single ID, return first (and should be only) child
     * in the response listing data.
     *
     * @param  Context  $context
     * @param  string  $redditId  Ex: t5_2sdu8
     *
     * @return array
     */
    public function getRedditItemInfoById(Context $context, string $redditId): array
    {
        $childrenResponseData = $this->getRedditItemInfoByIds($context, [$redditId]);

        return $childrenResponseData[0];
    }

    /**
     * This is an all-purpose call to retrieve information about any Reddit
     * items (Post, Comment, Subreddit, etc.) using their full Reddit IDs.
     *
     * @param  Context  $context
     * @param  array  $redditIds  Ex: [t3_vepbt0, t5_2sdu8, t1_ia1smh6]
     *
     * @return array
     */
    public function getRedditItemInfoByIds(Context $context, array $redditIds): array
    {
        $redditIdsGroups = array_chunk($redditIds, self::INFO_BATCH_SIZE);
        $allRetrievedItemsInfo = [];

        foreach ($redditIdsGroups as $redditIdsGroup) {
            $redditIdsString = implode(',', $redditIdsGroup);
            $endpoint = sprintf(self::INFO_ENDPOINT, $redditIdsString);
            $responseData = $this->executeCall($context,self::METHOD_GET, $endpoint)->toArray();

            $allRetrievedItemsInfo = [...$allRetrievedItemsInfo, ...$responseData['data']['children']];
        }

        return $allRetrievedItemsInfo;
    }

    /**
     * Core function which executes a call to the Reddit API.
     *
     * @param  Context  $context
     * @param  string  $method
     * @param  string  $endpoint
     * @param  array  $options
     * @param  bool  $retry
     *
     * @return ResponseInterface
     */
    private function executeCall(Context $context, string $method, string $endpoint, array $options = [], bool $retry = false): ResponseInterface
    {
        if (isset($this->accessToken)) {
            $options['auth_bearer'] = $this->accessToken;
        } else {
            $options['auth_bearer'] = $this->getAccessToken($context);
        }

        if (empty($options['headers'])) {
            $options['headers'] = [];
        }

        $options['headers']['User-Agent'] = $this->userAgent;

        $response = $this->client->request($method, $endpoint, $options);
        $this->eventDispatcher->dispatch(new RedditApiCallEvent($context, $method, $endpoint, $response, $options), RedditApiCallEvent::NAME);

        if ($response->getStatusCode() === 401 && $retry === false) {
            $this->refreshToken($context);
            return $this->executeCall($context, $method, $endpoint, $options, true);
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
     * @deprecated This function was originally used for unauthenticated calls to the Reddit API. Use executeCall().
     *
     * @param  Context  $context
     * @param  string  $method
     * @param  string  $endpoint
     * @param  array  $options
     *
     * @return ResponseInterface
     * @throws Exception
     */
    private function executeSimpleCall(Context $context, string $method, string $endpoint, array $options = []): ResponseInterface
    {
        throw new Exception('Deprecated. Use Authorized API calls only.');
    }

    /**
     * Attempt to the retrieve the current Access Token for the configured user.
     *
     * First, attempt to retrieve the token from the database. If no token
     * found, generate a new one from the Reddit API.
     *
     * @param  Context  $context
     *
     * @return string
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function getAccessToken(Context $context): string
    {
        if (!isset($this->accessToken) || empty($this->accessToken)) {
            $this->accessToken = $this->apiUserRepository->getAccessTokenByUsername($this->username);

            if (empty($this->accessToken)) {
                return $this->refreshToken($context);
            }
        }

        return $this->accessToken;
    }

    /**
     * Generate a fresh Access Token from the Reddit API for the current user.
     * Also, persist the new token to the database once generated.
     *
     * @param  Context  $context
     *
     * @return string
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function refreshToken(Context $context)
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
        $this->eventDispatcher->dispatch(new RedditApiCallEvent($context,self::METHOD_POST, self::OAUTH_ENDPOINT, $response, $options), RedditApiCallEvent::NAME);

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
     * This sanitation can accommodate full URLs and partial URIs such as the following:
     *
     * 1. /r/gaming/comments/xj8f7g/star_citizen_passes_half_billion_dollars_funding/
     * 2. https://www.reddit.com/r/gaming/comments/xj8f7g/star_citizen_passes_half_billion_dollars_funding/
     *
     * The return results of both examples are as follows, respectively:
     *
     * 1. https://reddit.com/r/gaming/comments/xj8f7g/star_citizen_passes_half_billion_dollars_funding.json
     * 2. https://www.reddit.com/r/gaming/comments/xj8f7g/star_citizen_passes_half_billion_dollars_funding.json
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

        $redditDomainExists = strrpos($postLink, 'reddit.com');
        if ($redditDomainExists === false) {
            // Remove leading slash, if any.
            $sanitizedPostLink = ltrim($sanitizedPostLink, '/');

            $sanitizedPostLink = 'https://oauth.reddit.com/' . $sanitizedPostLink;
        }

        // Ensure OAuth domain is targeted.
        $sanitizedPostLink = str_replace('https://www.reddit', 'https://oauth.reddit', $sanitizedPostLink);

        return $sanitizedPostLink;
    }

    /**
     * Build the URL for the retrieving `more children` data based on the
     * provided Post Reddit ID and array of target children Reddit IDs.
     *
     * @param  string  $postRedditId
     * @param  array  $childrenArray
     *
     * @return string
     */
    private function buildMoreChildrenUrl(string $postRedditId, array $childrenArray): string
    {
        $childrenString = implode(',', $childrenArray);

        return sprintf(self::MORE_CHILDREN_ENDPOINT, $postRedditId, $childrenString);
    }
}
