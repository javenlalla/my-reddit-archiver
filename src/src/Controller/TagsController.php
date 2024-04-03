<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tag;
use App\Form\ContentTagsForm;
use App\Form\TagForm;
use App\Repository\ContentRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tags', name: 'tags_')]
class TagsController extends AbstractController
{
    /**
     * Create and manage Tags.
     *
     * @return Response
     */
    #[Route('/', name: 'index')]
    public function viewTags(Request $request, TagRepository $tagRepository): Response
    {
        $tag = null;
        $editTagName = $request->get('tag');
        if (!empty($editTagName)) {
            $tag = $tagRepository->findOneBy(['name' => $editTagName]);
        }

        $form = $this->createForm(TagForm::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Tag $tag */
            $tag = $form->getData();
            $tagRepository->add($tag, true);

            $this->addFlash(
                'success',
                sprintf('Tag `%s` has been saved.', $tag->getName())
            );

            return $this->redirectToRoute('tags');
        }

        return $this->render('tags/view.html.twig', [
            'form' => $form,
            'tags' => $tagRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    /**
     * Surface the Tags associated to specified Content in an inline view.
     *
     * @param  Request  $request
     * @param  int  $contentId
     * @param  TagRepository  $tagRepository
     * @param  ContentRepository  $contentRepository
     *
     * @return Response
     */
    #[Route('/contents/{contentId}/inline', name: 'inline')]
    public function inlineContentTags(Request $request, int $contentId, TagRepository $tagRepository, ContentRepository $contentRepository): Response
    {
        $content = $contentRepository->find($contentId);

        return $this->render('tags/inline.html.twig', [
            'content' => $content,
        ]);
    }

    /**
     * Surface an in-line form to edit the Tags associated to the specified
     * Content.
     *
     * @param  Request  $request
     * @param  int  $contentId
     * @param  TagRepository  $tagRepository
     * @param  ContentRepository  $contentRepository
     * @param  EntityManagerInterface  $entityManager
     *
     * @return Response
     */
    #[Route('/contents/{contentId}/inline-edit', name: 'inline_edit')]
    public function inlineEditContentTags(Request $request, int $contentId, TagRepository $tagRepository, ContentRepository $contentRepository, EntityManagerInterface $entityManager): Response
    {
        $content = $contentRepository->find($contentId);

        $form = $this->createForm(ContentTagsForm::class, ['tags' => $content->getTags()->toArray()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $content->getTags()->clear();
            $formData = $form->getData();

            /** @var Tag[] $updatedTags */
            $updatedTags = $form->get('tags')->getData();
            foreach ($updatedTags as $tag) {
                $content->addTag($tag);
            }

            $entityManager->persist($content);
            $entityManager->flush();

            return $this->redirectToRoute('tags_inline', ['contentId' => $contentId]);
        }

        return $this->render('tags/inline_edit.html.twig', [
            'content' => $content,
            'contentTagsForm' => $form,
        ]);
    }

}
