<?php

namespace App\Service\RedditApi;

class Comment
{
    private string $id;

    private int $score;

    private string $text;

    /** @var Comment[] */
    private array $replies = [];

    public function __construct(private readonly array $rawCommentData)
    {
        $this->init();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return Comment[]
     */
    public function getReplies(): array
    {
        return $this->replies;
    }

    public function hasReplies(): bool
    {
        return !empty($this->replies);
    }

    private function init()
    {
        $this->id = $this->rawCommentData['id'];
        $this->score = (int) $this->rawCommentData['score'];
        $this->text = $this->rawCommentData['body'];
        if (!empty($this->rawCommentData['replies'])) {
            $replies = new Comments($this->rawCommentData['replies']);
            $this->replies = $replies->getComments();
        }
    }

    public function toJson(): array
    {
        return [
            'id' => $this->id,
            'score' => $this->score,
            'text' => $this->text,
            'hasReplies' => $this->hasReplies(),
            'replies' => $this->getRepliesJson(),
        ];
    }

    public function getRepliesJson(): array
    {
        $replies = [];
        foreach ($this->replies as $reply) {
            $replies[] = $reply->toJson();
        }

        return $replies;
    }
}