<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\Content;
use App\Entity\Tag;
use App\Form\ContentTagsForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsLiveComponent('content_tags')]
class ContentTagsComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?Content $content = null;

    #[LiveProp(writable: true)]
    public array $tags = [];

    #[LiveProp(writable: true)]
    public bool $editMode = false;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Ensure the Tags property is set to the Content's current Tags to prevent
     * clearing the Tags unintentionally if the user hits "Save" without making
     * any changes to the Tags form.
     *
     * @param  array{
     *      content: Content,
     *      tags: array,
     *   }  $data
     *
     * @return array
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        foreach ($data['content']->getTags() as $tag) {
            $data['tags'][] = $tag->getName();
        }

        return $data;
    }

    #[LiveAction]
    public function saveTags(): void
    {
        $this->content->getTags()->clear();

        foreach ($this->tags as $tagName) {
            $tag = $this->entityManager
                ->getRepository(Tag::class)
                ->findOneBy(['name' => $tagName]
            );

            if (empty($tag)) {
                $tag = new Tag();
                $tag->setName($tagName);

                $this->entityManager->persist($tag);
            }

            $this->content->addTag($tag);
        }

        $this->entityManager->persist($this->content);
        $this->entityManager->flush();
    }

    #[LiveAction]
    public function toggleEditMode(): void
    {
        $this->editMode = !$this->editMode;
    }

    public function getContentTagsForm(): FormView
    {
        return $this->createForm(ContentTagsForm::class, ['tags' => $this->content->getTags()])->createView();
    }
}
