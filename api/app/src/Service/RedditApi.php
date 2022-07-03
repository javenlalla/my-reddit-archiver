<?php

namespace App\Service;

use App\Repository\ApiUserRepository;
use App\Service\RedditApi\Comments;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @property HttpClientInterface $client
 */
class RedditApi
{
    const SAVED_POSTS_BASE_URL = 'https://oauth.reddit.com/user/%s/saved';

    private string $accessToken;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ApiUserRepository $apiUserRepository,
        private readonly string $username,
        private readonly string $password,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
        $this->accessToken = $this->getAccessToken();
    }

    public function getSavedPosts(): array
    {
        return $this->client
            ->request('GET', $this->getSavedPostsUrl() . '?limit=1',
                [
                    'auth_bearer' => $this->accessToken,
                    'headers' => [
                        'User-Agent' => uniqid(),
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
                        'User-Agent' => uniqid(),
                    ]
                ]
            )
            ->toArray();

        $opComment = $comments[0];
        $comments = new Comments($comments[1]['data']['children']);

        return $comments->toJson();
    }

    private function getSavedPostsUrl(): string
    {
        return sprintf(self::SAVED_POSTS_BASE_URL, $this->username);
    }

    private function getAccessToken()
    {
        if (empty($this->accessToken)) {
            // @TODO: Add error handling and refresh logic.
            $this->accessToken = $this->apiUserRepository->getAccessTokenByUsername($this->username);
        }

        return $this->accessToken;
    }
}
