<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\FlairText;
use App\Entity\Post;
use App\Entity\Subreddit;
use App\Entity\Tag;
use App\Form\SearchForm;
use App\Service\Pagination;
use App\Service\Pagination\Paginator;
use App\Service\Search;
use App\Service\Search\Results;
use Doctrine\ORM\EntityManagerInterface;
use Http\Client\Exception;
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
    public ?string $query = '';

    #[LiveProp(writable: true)]
    public array $subreddits = [];

    #[LiveProp(writable: true)]
    public array $flairTexts = [];

    #[LiveProp(writable: true)]
    public array $tags = [];

    #[LiveProp(writable: true)]
    public int $perPage = Search::DEFAULT_LIMIT;

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly Search $searchService,
        private readonly EntityManagerInterface $entityManager,
        private readonly Pagination $paginationService
    ) {
    }

    /**
     * Instantiate the Search Form.
     *
     * @return FormView
     */
    public function getSearchForm(): FormView
    {
        $subredditEntities = [];
        foreach ($this->subreddits as $subreddit) {
            $subredditEntity = $this->entityManager->getRepository(Subreddit::class)->findOneBy(['name' => $subreddit]);
            if (!empty($subredditEntity)) {
                $subredditEntities[] = $subredditEntity;
            }
        }

        $flairTextEntities = [];
        foreach ($this->flairTexts as $flairText) {
            $flairTextEntity = $this->entityManager->getRepository(FlairText::class)->findOneBy(['referenceId' => $flairText]);
            if (!empty($flairTextEntity)) {
                $flairTextEntities[] = $flairTextEntity;
            }
        }

        $tagEntities = [];
        foreach ($this->tags as $tag) {
            $tagEntity = $this->entityManager->getRepository(Tag::class)->findOneBy(['name' => $tag]);
            if (!empty($tagEntity)) {
                $tagEntities[] = $tagEntity;
            }
        }

        return $this->createForm(SearchForm::class, [
            'subreddits' => $subredditEntities,
            'flairTexts' => $flairTextEntities,
            'tags' => $tagEntities,
        ])->createView();
    }

    /**
     * Execute a Search using the currently inputted search parameters.
     *
     * @return Results
     * @throws Exception
     */
    public function getSearchResults(): Results
    {
        return $this->searchService->search(
            $this->query,
            $this->subreddits,
            $this->flairTexts,
            $this->tags,
            $this->perPage,
            $this->page,
        );
    }

    /**
     * Generate and return a Paginator based on the current pagination
     * parameters and total Search results.
     *
     * @param  int  $totalResults
     *
     * @return Paginator
     */
    public function getPaginator(int $totalResults = 0): Paginator
    {
        return $this->paginationService->createNewPaginator(
            $totalResults,
            $this->perPage,
            $this->page,
        );
    }
}
