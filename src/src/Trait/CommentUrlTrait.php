<?php
declare(strict_types=1);

namespace App\Trait;

use App\Entity\Comment;
use App\Entity\Post;

trait CommentUrlTrait
{
    /**
     * Generate the direct URL linked to the provided Comment and set it to
     * the Comment Entity.
     *
     * @param  Post  $post
     * @param  string  $commentRedditId
     *
     * @return string
     */
    public function generateRedditUrl(Post $post, string $commentRedditId): string
    {
        return sprintf(Comment::REDDIT_URL_FORMAT,
            $post->getSubreddit()->getName(),
            $post->getRedditId(),
            $commentRedditId,
        );
    }
}
