<?php
declare(strict_types=1);

namespace App\Controller;

use App\Form\TagForm;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TagsController extends AbstractController
{
    /**
     * View all created Tags and their metadata.
     *
     * @return Response
     */
    #[Route('/tags', name: 'tags')]
    public function viewTags(Request $request, EntityManagerInterface $entityManager, TagRepository $tagRepository): Response
    {
        $form = $this->createForm(TagForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tagRepository->add($form->getData(), true);

            $entityManager->flush();

            return $this->redirectToRoute('tags');
        }

        return $this->renderForm('tags/view.html.twig', ['form' => $form]);
    }
}
