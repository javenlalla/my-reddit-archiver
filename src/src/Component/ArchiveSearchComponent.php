<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\Post;
use App\Entity\Subreddit;
use App\Entity\Tag;
use App\Form\SearchForm;
use App\Service\Search;
use Doctrine\ORM\EntityManagerInterface;
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
    public int $limit = 50;

    public function __construct(private readonly Search $searchService, private readonly EntityManagerInterface $entityManager)
    {
    }

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
            $flairTextEntity = $this->entityManager->getRepository(Post::class)->findOneBy(['flairText' => $flairText]);
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

    public function getContents(): array
    {
        return $this->searchService->search($this->query, $this->subreddits, $this->flairTexts, $this->tags);
    }
}
