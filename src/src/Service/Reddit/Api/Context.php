<?php
declare(strict_types=1);

namespace App\Service\Reddit\Api;

use JsonSerializable;

/**
 * Class to contain contextual information related to a call to the Reddit API.
 */
class Context implements JsonSerializable
{
    const SOURCE_USER_SYNC_COMMENTS = 'UserTriggered: Sync Comments';

    const SOURCE_USER_SYNC_COMMENT_CHILDREN = 'UserTriggered: Sync Comment Children';

    public function __construct(private readonly string $source)
    {

    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'source' => $this->source,
        ];
    }
}
