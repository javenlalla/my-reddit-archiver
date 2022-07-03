<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RedditApi
{
    /**
     * @param  HttpClientInterface  $client
     */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
}