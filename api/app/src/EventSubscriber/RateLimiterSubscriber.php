<?php

namespace App\EventSubscriber;

use App\Event\RedditApiCallEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RateLimiterFactory $redditApiLimiter)
    {
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            RedditApiCallEvent::NAME => 'onApiCall',
        ];
    }

    public function onApiCall(RedditApiCallEvent $event)
    {
        $limiter = $this->redditApiLimiter->create($event->getUsername());

        if (false === $limiter->consume(1)->isAccepted()) {
            // @TODO: Replace generic messages with proper logging.
            echo "Limit reached. Waiting.";
            $limiter->reserve(1)->wait();
            echo "Done waiting.";
        }
    }
}
