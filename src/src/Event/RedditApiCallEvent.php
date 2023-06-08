<?php
declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\HttpClient\ResponseInterface;

class RedditApiCallEvent extends Event
{
    public const NAME = 'reddit_api.call';

    private ResponseInterface $response;

    private string $method;

    private string $endpoint;

    private array $options = [];

    public function __construct(string $method, string $endpoint, ResponseInterface $response, array $options = [])
    {
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->response = $response;
        $this->options = $options;
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
}
