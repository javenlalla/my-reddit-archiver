<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\FlairText;
use App\Entity\Subreddit;
use App\Entity\Tag;
use App\Form\SearchForm;
use App\Form\SearchFormV2;
use App\Service\Pagination;
use App\Service\Search;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(Request $request): Response
    {
        return $this->render('home/home.html.twig', [
            'query' => $request->get('query'),
            'subreddits' => $request->get('subreddits', []),
            'flairTexts' => $request->get('flairTexts', []),
            'tags' => $request->get('tags', []),
            'perPage' => (int) $request->get('perPage', Search::DEFAULT_LIMIT),
            'page' => (int) $request->get('page', 1),
        ]);
    }

    #[Route('/v2/search', name: 'search.v2')]
    public function searchUpdated(Request $request, Search $searchService, Pagination $paginationService, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SearchFormV2::class, [
            'subreddits' => [],
            'flairTexts' => [],
            'tags' => [],
        ], [
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);

        $page = (int) $request->get('page', 1);
        $form->handleRequest($request);
        /** @var SearchForm $searchCriteria */
        $searchCriteria = $form->getData();

        $searchResults = $searchService->search(
            $searchCriteria['query'] ?? null,
            $searchCriteria['subreddits'],
            $searchCriteria['flairTexts'],
            $searchCriteria['tags'],
            $request->get('perPage', Search::DEFAULT_LIMIT),
            page: $page,
        );

        $paginator = $paginationService->createNewPaginator(
            $searchResults->getTotal(),
            $searchResults->getPerPage(),
            $searchResults->getPage(),
            $request->getBasePath(),
            $request->query->all(),
        );

        return $this->render('home/home2.html.twig', [
            'form' => $form,
            'searchResults' => $searchResults,
            'paginator' => $paginator,
        ]);
    }
}
