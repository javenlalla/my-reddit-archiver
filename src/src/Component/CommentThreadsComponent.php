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
use Symfony\UX\TwigComponent\Attribute\PreMount;

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

    /**
     * @param  array{
     *      content: Content,
     *      comments: array,
     *      }  $data
     *
     * @return array
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $data['comments'] = $data['content']->getPost()->getTopLevelComments()->toArray();

        return $data;
    }

    #[LiveAction]
    public function syncComments()
    {
        $this->comments = $this->commentsManager->syncCommentsByContent($this->content)->toArray();
    }
}
