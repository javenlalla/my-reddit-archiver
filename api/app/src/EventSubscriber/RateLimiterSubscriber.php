<?php

namespace App\EventSubscriber;

use App\Event\RedditApiCallEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Event Subscriber to track calls to the Reddit API in order to avoid exceeding
 * the rate limit.
 */
class RateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RateLimiterFactory $redditApiLimiter, private readonly LoggerInterface $logger)
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
            $this->logger->info('API Limit reached. Waiting.');

            $limiter->reserve(1)->wait();

            $this->logger->info('Waiting completed. Proceeding.');
        }
    }
}
