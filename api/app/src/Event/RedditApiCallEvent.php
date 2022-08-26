<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class RedditApiCallEvent extends Event
{
    public const NAME = 'reddit_api.call';

    private string $username;

    public function __construct(string $username)
    {
        $this->username = $username;
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
