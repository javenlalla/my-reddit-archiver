<?php

namespace App\Service\RedditApi;

class Comments
{
    /**
     * @var Comment[]
     */
    private array $comments = [];

    public function __construct(
        private readonly array $commentRawData = [],
    ) {
        $this->init();
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function toJson(): array
    {
        $comments = [];
        foreach ($this->comments as $comment) {
            $comments[] = $comment->toJson();
        }

        return $comments;
    }

    private function init()
    {
        $targetChildren = $this->commentRawData;
        if (!empty($this->commentRawData['data'])) {
            $targetChildren = $this->commentRawData['data']['children'];
        }

        foreach ($targetChildren as $parentComment) {
            $this->comments[] = new Comment($parentComment['data']);
        }
    }
}