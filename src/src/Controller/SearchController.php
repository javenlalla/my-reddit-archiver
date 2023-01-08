<?php
declare(strict_types=1);

namespace App\Controller;

use App\Form\SearchForm;
use App\Service\Search;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    /**
     * @param  Request  $request
     * @param  Search  $searchService
     *
     * @return Response
     */
    #[Route('/search', name: 'search')]
    public function getContents(Request $request, Search $searchService): Response
    {
        $searchQuery = $request->query->get('q');

        $subreddits = [];
        $subredditsParam = $request->query->get('subreddits');
        if (!empty($subredditsParam)) {
            $subreddits = explode(',', $subredditsParam);
        }

        $flairTexts = [];
        $flairTextsParam = $request->query->get('flairTexts');
        if (!empty($flairTextsParam)) {
            $flairTexts = explode(',', $flairTextsParam);
        }

        $searchResults = $searchService->search($searchQuery, $subreddits, $flairTexts);

        return $this->render('search-results.html.twig', [
            'contents' => $searchResults,
        ]);
    }

    #[Route('/search-form', name: 'search-form')]
    public function searchForm(Request $request, Search $searchService): Response
    {
        $searchForm = $this->createForm(SearchForm::class);
        $searchForm->handleRequest($request);

        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchData = $searchForm->getData();

            $searchQuery = $searchData['query'];

            $subreddits = [];
            $subredditsParam =  $searchData['subreddits'];
            if (!empty($subredditsParam)) {
                $subreddits = explode(',', $subredditsParam);
            }

            $flairTexts = [];
            $flairTextsParam = $searchData['flairTexts'];
            if (!empty($flairTextsParam)) {
                $flairTexts = explode(',', $flairTextsParam);
            }

            $searchResults = $searchService->search($searchQuery, $subreddits, $flairTexts);
        } else {
            $searchResults = $searchService->search(null);
        }

        return $this->render('search/results.html.twig', [
            'searchResults' => $searchResults,
            'searchForm' => $searchForm->createView(),
        ]);
    }
}
