<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
