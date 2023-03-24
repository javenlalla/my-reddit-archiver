<?php
declare(strict_types=1);

namespace App\Helper;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;

class FullRedditIdHelper
{
    /**
     * Generate and return the full Reddit ID associated to the provided Content
     * based on its Post and Comment associations.
     *
     * @param  Content  $content
     *
     * @return string
     */
    public function getFullRedditIdFromContent(Content $content): string
    {
        $redditId = $content->getPost()->getRedditId();
        $comment = $content->getComment();
        if ($comment instanceof Comment) {
            $redditId = $comment->getRedditId();
        }

        return $this->formatFullRedditIdWithKind($content->getKind(), $redditId);
    }

    /**
     * Generate and return the full Reddit ID based on the provided Kind Entity
     * and Reddit ID.
     *
     * @param  Kind  $kind
     * @param  string  $redditId
     *
     * @return string
     */
    public function formatFullRedditIdWithKind(Kind $kind, string $redditId): string
    {
        return $this->formatFullRedditId(
            $kind->getRedditKindId(),
            $redditId,
        );
    }

    /**
     * Using the provided parameters, generate and return a full Reddit ID in the
     * format of: t#_abcdefg
     *
     * @param  string  $kindRedditId
     * @param  string  $redditId
     *
     * @return string
     */
    public function formatFullRedditId(string $kindRedditId, string $redditId): string
    {
        return $kindRedditId . '_' . $redditId;
    }
}
