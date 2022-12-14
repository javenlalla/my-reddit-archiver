<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Denormalizer\CommentDenormalizer;
use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;
use App\Service\Reddit\Api;
use Psr\Cache\InvalidArgumentException;

class Comments
{
    public function __construct(private readonly Api $redditApi, private readonly CommentDenormalizer $commentDenormalizer)
    {
    }

    /**
     * Retrieve the latest Comment on the provided Content by calling the API
     * and sorting by `New`.
     *
     * @param  Content  $content
     *
     * @return Comment|null
     * @throws InvalidArgumentException
     */
    public function getLatestCommentByContent(Content $content): ?Comment
    {
        $commentsRawResponse = $this->redditApi->getPostCommentsByRedditId(
            redditId: $content->getPost()->getRedditId(),
            sort: Api::COMMENTS_SORT_NEW,
        );

        $commentData = [];
        foreach ($commentsRawResponse as $topLevelRaw) {
            foreach ($topLevelRaw['data']['children'] as $topLevelChildRaw) {
                if ($topLevelChildRaw['kind'] === Kind::KIND_COMMENT) {
                    $commentData = $topLevelChildRaw['data'];
                }
            }
        }

        // If no Comment data found (i.e.: Link Post contained no Comments),
        // return null.
        if (empty($commentData)) {
            return null;
        }

        return $this->commentDenormalizer->denormalize($content, Comment::class, null, ['commentData' => $commentData]);
    }
}
