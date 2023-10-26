<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tag;
use App\Form\TagForm;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TagsController extends AbstractController
{
    /**
     * Create and manage Tags.
     *
     * @return Response
     */
    #[Route('/tags', name: 'tags')]
    public function viewTags(Request $request, TagRepository $tagRepository): Response
    {
        $form = $this->createForm(TagForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Tag $tag */
            $tag = $form->getData();
            $tagRepository->add($tag, true);

            $this->addFlash(
                'success',
                sprintf('Tag `%s` has been created.', $tag->getName())
            );

            return $this->redirectToRoute('tags');
        }

        return $this->render('tags/view.html.twig', [
            'form' => $form,
            'tags' => $tagRepository->findBy([], ['name' => 'ASC']),
        ]);
    }
}
