<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\Comment;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager\Comments;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('comment_thread')]
class CommentThreadComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public Comment $comment;

    public function __construct(private readonly Comments $commentsManager)
    {
    }

    #[LiveAction]
    public function syncLoadMore(#[LiveArg] string $redditId)
    {
        $context = new Context(Context::SOURCE_USER_SYNC_COMMENT_CHILDREN);
        $comments = $this->commentsManager->syncMoreCommentAndRelatedByRedditId($context, $redditId);
    }
}