<?php

namespace App\Service\RedditApi;

use App\Service\RedditApi;

class Post
{
    const TYPE_COMMENT = 't1';

    const TYPE_LINK = 't3';

    const CONTENT_TYPE_IMAGE = 'image';

    const CONTENT_TYPE_VIDEO = 'video';

    private string $redditId;

    private string $type;

    private string $title;

    private string $contentType;

    private int $score;

    private string $url;

    public function __construct(array $rawData = [])
    {
        if (!empty($rawData)) {
            $this->initFromRawData($rawData);
        }
    }

    public function getRedditId(): ?string
    {
        return $this->redditId;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    private function initFromRawData(array $rawData)
    {
        //@TODO: Create array validator using: https://symfony.com/doc/current/validation/raw_values.html
        if ($rawData['kind'] === self::TYPE_LINK) {
            $this->initLinkPostFromRawData($rawData);
        } elseif ($rawData['kind'] === self::TYPE_COMMENT) {
            $this->initCommentPostFromRawData($rawData);
        } else {
            throw new \Exception(sprintf('Unexpected Post type %s: %s', $rawData['kind'], var_export($rawData, true)));
        }
    }

    private function initLinkPostFromRawData(array $rawData)
    {
        $postData = $rawData['data'];
        $this->redditId = $postData['id'];
        $this->type = self::TYPE_LINK;
        $this->title = $postData['title'];
        $this->score = (int) $postData['score'];

        if ($postData['domain'] === 'i.imgur.com' || !empty($postData['preview']['images'])) {
            $this->contentType = self::CONTENT_TYPE_IMAGE;
        }

        $this->url = $postData['url'];
    }

    private function initCommentPostFromRawData(array $rawData)
    {

    }
}
