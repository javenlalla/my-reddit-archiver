<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentRepository;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager\Comments;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/contents', name: 'contents_')]
class ContentController extends AbstractController
{
    #[Route('/view/{id}', name: 'view_content')]
    public function viewContent(ContentRepository $contentRepository, int $id)
    {
        $content = $contentRepository->find($id);
        if (empty($content)) {
            throw $this->createNotFoundException('Requested Content not found.');
        }

        return $this->render('contents/view.html.twig', [
            'content' => $content,
        ]);
    }

    /**
     * AJAX endpoint to sync down the Comments of the Post associated to the
     * designated Content and render the Comments.
     *
     * @param  Request  $request
     * @param  ContentRepository  $contentRepository
     * @param  int  $id
     * @param  Comments  $commentsManager
     *
     * @return Response
     * @throws InvalidArgumentException
     */
    #[Route('/{id}/sync-comments', name: 'sync_comments', methods: ['POST'])]
    public function syncContentPosts(Request $request, ContentRepository $contentRepository, int $id, Comments $commentsManager): Response
    {
        $content = $contentRepository->find($id);
        if (empty($content)) {
            throw $this->createNotFoundException('Requested Content not found.');
        }

        $submittedToken = $request->getPayload()->get('token');
        if ($this->isCsrfTokenValid('sync-comments' . $content->getId(), $submittedToken)) {
            $context = new Context(Context::SOURCE_USER_SYNC_COMMENTS);
            $syncedComments = $commentsManager->syncCommentsByContent($context, $content)->toArray();
        }

        $comments = $commentsManager
            ->getOrderedCommentsByPost(
                $content->getPost(),
                true,
                true
            )
        ;

        return $this->render('contents/comments/_comments_threads.html.twig', [
            'content' => $content,
            'comments' => $comments,
        ]);
    }
}
