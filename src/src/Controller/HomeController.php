<?php
declare(strict_types=1);

namespace App\Controller;

use App\Form\SearchForm;
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
    public function home(Request $request, Search $searchService, Pagination $paginationService, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SearchForm::class, [
            'subreddits' => [],
            'flairTexts' => [],
            'tags' => [],
        ], [
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);

        $page = (int)$request->get('page', 1);
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

        return $this->render('home/home.html.twig', [
            'form' => $form,
            'searchResults' => $searchResults,
            'paginator' => $paginator,
        ]);
    }

    /**
     * @param  Request  $request
     * @param  Search  $searchService
     * @param  Pagination  $paginationService
     * @param  EntityManagerInterface  $em
     *
     * @return Response
     * @deprecated To be removed. This page uses the Archive Search Live Component
     * that has been deprecated and will be removed.
     *
     */
    #[Route('/search/deprecated', name: 'search.deprecated')]
    public function searchDeprecated(Request $request, Search $searchService, Pagination $paginationService, EntityManagerInterface $em): Response
    {
        return $this->render('home/home.html.twig', [
            'query' => $request->get('query'),
            'subreddits' => $request->get('subreddits', []),
            'flairTexts' => $request->get('flairTexts', []),
            'tags' => $request->get('tags', []),
            'perPage' => (int)$request->get('perPage', Search::DEFAULT_LIMIT),
            'page' => (int)$request->get('page', 1),
        ]);
    }
}
