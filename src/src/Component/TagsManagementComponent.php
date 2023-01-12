<?php
declare(strict_types=1);

namespace App\Component;

use App\Form\TagForm;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('tags_management')]
class TagsManagementComponent extends AbstractController
{
    use DefaultActionTrait;

    use ComponentWithFormTrait;

    #[LiveProp(writable: true)]
    public string $tagName = '';

    public function __construct(private readonly TagRepository $tagRepository)
    {
    }

    public function getTags(): array
    {
        return $this->tagRepository->findBy([], ['name' => 'ASC']);
    }

    /**
     * Used to re-create the PostType form for re-rendering.
     */
    protected function instantiateForm(): FormInterface
    {
        // we can extend AbstractController to get the normal shortcuts
        return $this->createForm(TagForm::class);
    }
}
