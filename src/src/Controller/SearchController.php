<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Search;
use Http\Client\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Typesense\Exceptions\TypesenseClientError;

class SearchController extends AbstractController
{
    /**
     * @param  Request  $request
     * @param  Search  $searchService
     *
     * @throws Exception
     * @throws TypesenseClientError
     */
    #[Route('/search', name: 'search')]
    public function getContents(Request $request, Search $searchService): Response
    {
        $searchQuery = $request->query->get('q', null);
        $searchResults = $searchService->search($searchQuery);

        return $this->render('search-results.html.twig', [
            'contents' => $searchResults,
        ]);

        // return $this->json([
        //     'data' => $searchResults,
        // ]);
    }
}
