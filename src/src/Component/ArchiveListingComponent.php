<?php
declare(strict_types=1);

namespace App\Component;

use App\Form\SearchForm;
use App\Service\Search;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent('archive_listing')]
class ArchiveListingComponent extends AbstractController
{
    public array $contents = [];

    public FormView $searchForm;

    public function __construct(private readonly Search $searchService)
    {
    }

    #[PreMount]
    public function preMount(array $data): array
    {
        if (empty($data['contents'])) {
            $data['contents'] = $this->searchService->search(null);
        }

        if (empty($data['searchForm'])) {
            $data['searchForm'] = $this->createForm(SearchForm::class)->createView();
        }

        return $data;
    }
}
