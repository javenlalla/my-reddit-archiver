<?php
declare(strict_types=1);

namespace App\Event;

use App\Service\Reddit\Api\Context;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\HttpClient\ResponseInterface;

class RedditApiCallEvent extends Event
{
    public const NAME = 'reddit_api.call';

    public function __construct(
        private readonly Context $context,
        private readonly string $method,
        private readonly string $endpoint,
        private readonly ResponseInterface $response,
        private readonly array $options = [],
    ) {
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }
}
