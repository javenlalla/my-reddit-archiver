<?php
declare(strict_types=1);

namespace App\Helper;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;
use Exception;

class RedditIdHelper
{
    /**
     * Regex pattern to target the Post Reddit ID within a URL.
     *
     * Example:
     *  - https://www.reddit.com/r/golang/comments/z2ngmf/comment/ixhzp48/ -> z2ngmf
     *  - /r/science/comments/wf1e8p/exercising_almost_daily_for_up_to_an_hour_at_a/iirwrq4/ -> wf1e8p
     */
    const URL_REDDIT_ID_REGEX = '/comments\/([a-z0-9]{4,10})/iu';

    /**
     * Regex pattern to target the Comment Reddit ID within a URL.
     *
     * Example:
     *  - https://www.reddit.com/r/golang/comments/z2ngmf/comment/ixhzp48/ -> ixhzp48
     *  - /r/science/comments/wf1e8p/exercising_almost_daily_for_up_to_an_hour_at_a/iirwrq4/ -> iirwrq4
     */
    const COMMENT_URL_REDDIT_ID_REGEX = '/comments\/[a-zA-Z0-9]{4,10}\/[[:word:]_]*\/([a-zA-Z0-9]{4,10})/iu';

    /**
     * Extract the Reddit ID from the provided URL.
     * The Kind provided allows the extraction to target either the Post ID or
     * the Comment ID (if the URL is a Comment URL) of the URL.
     *
     * @param  string  $redditKindId  The Kind designated for the ID to extract.
     * @param  string  $url
     *
     * @return string  The Reddit ID extracted from the URL: t#_abcdefg
     * @throws Exception
     */
    public function extractRedditIdFromUrl(string $redditKindId, string $url): string
    {
        $regex = self::URL_REDDIT_ID_REGEX;
        if ($redditKindId === Kind::KIND_COMMENT) {
            $regex = self::COMMENT_URL_REDDIT_ID_REGEX;
        }

        preg_match($regex, $url, $urlParts);
        if (!empty($urlParts[1])) {
            return $redditKindId . '_' . $urlParts[1];
        }

        throw new Exception(sprintf(
            'Unable to extract %s Reddit ID from URL: %s',
            $redditKindId,
            $url
        ));
    }

    /**
     * Generate and return the Reddit ID (t#_abcdefg) associated to the provided Content
     * based on its Post and Comment associations.
     *
     * @param  Content  $content
     *
     * @return string
     */
    public function getRedditIdFromContent(Content $content): string
    {
        $redditId = $content->getPost()->getRedditId();
        $comment = $content->getComment();
        if ($comment instanceof Comment) {
            $redditId = $comment->getRedditId();
        }

        return $this->formatRedditIdWithKind($content->getKind(), $redditId);
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
    public function formatRedditIdWithKind(Kind $kind, string $redditId): string
    {
        return $this->formatRedditId(
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
    public function formatRedditId(string $kindRedditId, string $redditId): string
    {
        return $kindRedditId . '_' . $redditId;
    }

    /**
     * Verify if the provided Reddit ID is a Comment ID based on the ID's
     * prefix.
     *
     * Examples:
     *      - t1_ip7pedq -> true
     *      - t3_abcdefg1 -> false
     *
     * @param  string  $redditId
     *
     * @return bool
     */
    public function isRedditIdCommentId(string $redditId): bool
    {
        $targetPrefix = Kind::KIND_COMMENT . '_';

        return str_starts_with($redditId, $targetPrefix);
    }
}
