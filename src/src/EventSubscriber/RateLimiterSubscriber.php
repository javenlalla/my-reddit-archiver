<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ApiCallLog;
use App\Event\RedditApiCallEvent;
use App\Repository\ApiCallLogRepository;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Event Subscriber to track calls to the Reddit API and in order to avoid exceeding
 * the rate limit.
 */
class RateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $redditUsername,
        private readonly RateLimiterFactory $redditApiLimiter,
        private readonly ApiCallLogRepository $apiCallLogRepository,
        private readonly LoggerInterface $logger
    ) {
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

    /**
     * On an API Call Event, create a log entry for the call and track against
     * the Rate Limiter.
     *
     * @param  RedditApiCallEvent  $event
     *
     * @return void
     */
    public function onApiCall(RedditApiCallEvent $event): void
    {
        $this->logCall($event);
        $this->trackRateLimit($event);
    }

    /**
     * Retrieve the API call data from the Event and persist a new log entry to
     * the database.
     *
     * @param  RedditApiCallEvent  $event
     *
     * @return void
     */
    private function logCall(RedditApiCallEvent $event): void
    {
        try {
            $callLog = new ApiCallLog();
            $callLog->setEndpoint($event->getEndpoint());
            $callLog->setMethod($event->getMethod());
            $callLog->setCallData(json_encode($event->getOptions()));
            $callLog->setCreatedAt(new DateTimeImmutable());
            $callLog->setResponse(json_encode($event->getResponse()->toArray()));

            $this->apiCallLogRepository->add($callLog, true);
        } catch (Exception $e) {
            $this->logger->error(sprintf('Error persisting new API call log: %s' ,$e->getMessage()));
        }
    }

    /**
     * Record the current API call against the tracking rate limit and pause
     * as needed.
     *
     * @return void
     */
    private function trackRateLimit(): void
    {
        $limiter = $this->redditApiLimiter->create($this->redditUsername);
        if (false === $limiter->consume(1)->isAccepted()) {
            $this->logger->info('API Limit reached. Waiting.');

            $limiter->reserve(1)->wait();

            $this->logger->info('Waiting completed. Proceeding.');
        }
    }
}
