<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\Content;
use App\Entity\Post;
use App\Service\Reddit\Api\Context;
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
        $data['comments'] = $this->getCommentsByPost($post);

        return $data;
    }

    #[LiveAction]
    public function syncComments()
    {
        $context = new Context(Context::SOURCE_USER_SYNC_COMMENTS);
        $syncedComments = $this->commentsManager->syncCommentsByContent($context, $this->content)->toArray();

        $post = $this->content->getPost();
        $this->comments = $this->getCommentsByPost($post);
    }

    /**
     * Fetching the Comments for this Component happens in multiple places.
     * This function consolidates the fetching logic under one function, so if
     * the logic changes or requires updating, it only needs to be done in one
     * place (here).
     *
     * @param  Post  $post
     *
     * @return array
     */
    private function getCommentsByPost(Post $post): array
    {
        return $this->commentsManager
            ->getOrderedCommentsByPost(
                $post,
                true,
                true
            )
        ;
    }
}
