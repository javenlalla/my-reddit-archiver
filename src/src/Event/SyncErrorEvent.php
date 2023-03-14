<?php
declare(strict_types=1);

namespace App\Event;

use Exception;
use Symfony\Contracts\EventDispatcher\Event;

class SyncErrorEvent extends Event
{
    public const NAME = 'sync.error';

    public const TYPE_CONTENT = 'content';

    public const TYPE_ASSET = 'asset';

    private Exception $exception;

    private string $syncType;

    private array $additionalData;

    public function __construct(Exception $e, string $syncType, array $additionalData = [])
    {
        $this->exception = $e;
        $this->syncType = $syncType;
        $this->additionalData = $additionalData;
    }

    public function getException(): Exception
    {
        return $this->exception;
    }

    public function getSyncType(): string
    {
        return $this->syncType;
    }

    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }
}
