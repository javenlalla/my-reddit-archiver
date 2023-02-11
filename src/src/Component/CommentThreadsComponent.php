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
        $post = $data['content']->getPost();
        $data['comments'] = $this->commentsManager->getOrderedCommentsByPost($post);

        return $data;
    }

    #[LiveAction]
    public function syncComments()
    {
        $syncedComments = $this->commentsManager->syncCommentsByContent($this->content)->toArray();

        $post = $this->content->getPost();
        $this->comments = $this->commentsManager->getOrderedCommentsByPost($post);
    }
}
