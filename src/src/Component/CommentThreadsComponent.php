<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\Content;
use App\Service\Reddit\Manager\Comments;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent('comment_threads')]
class CommentThreadsComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public Content $content;

    public array $comments = [];

    public function __construct(private readonly Comments $commentsManager)
    {
    }

    #[LiveAction]
    public function syncComments()
    {
        $this->comments = $this->commentsManager->syncAllCommentsByContent($this->content)->toArray();
    }
}
