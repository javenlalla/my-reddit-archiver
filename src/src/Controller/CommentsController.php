<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\CommentRepository;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager\Comments;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/comments', name: 'comments_')]
class CommentsController extends AbstractController
{
    /**
     * Convert a video to another format.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    #[Route('/{commentId}/load-more/{moreCommentId}', name: 'load_more', methods: ['POST'])]
    public function convertVideo(Request $request, int $commentId, string $moreCommentId, Comments $commentsManager, CommentRepository $commentRepository): Response
    {
        $submittedToken = $request->getPayload()->get('token');
        if ($this->isCsrfTokenValid('load-more' . $commentId, $submittedToken)) {
            $context = new Context(Context::SOURCE_USER_SYNC_COMMENT_CHILDREN);
            $comments = $commentsManager->syncMoreCommentAndRelatedByRedditId($context, $moreCommentId);
        }

        $comment = $commentRepository->find($commentId);

        return $this->render('comments/thread.html.twig', [
            'comment' => $comment,
        ]);
    }
}