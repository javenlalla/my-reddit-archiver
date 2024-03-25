<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\Content;
use App\Entity\Post;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager\Comments;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent('comment_threads')]
class CommentThreadsComponent extends AbstractController
{
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
