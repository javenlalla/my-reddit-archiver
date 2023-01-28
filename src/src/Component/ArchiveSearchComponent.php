<?php
declare(strict_types=1);

namespace App\Component;

use App\Form\SearchForm;
use App\Service\Search;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent('archive_search')]
class ArchiveSearchComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public array $subreddits = [];

    #[LiveProp(writable: true)]
    public array $flairTexts = [];

    #[LiveProp(writable: true)]
    public array $tags = [];

    public function __construct(private readonly Search $searchService)
    {
    }

    public function getSearchForm(): FormView
    {
        return $this->createForm(SearchForm::class)->createView();
    }

    public function getContents(): array
    {
        return $this->searchService->search($this->query, $this->subreddits, $this->flairTexts, $this->tags);
    }
}
