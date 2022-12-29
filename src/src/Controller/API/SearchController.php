<?php
declare(strict_types=1);

namespace App\Controller\API;

use App\Service\Typesense\Search;
use Http\Client\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Typesense\Exceptions\TypesenseClientError;

#[Route('/api', name: 'api_')]
class SearchController extends AbstractController
{
    /**
     * @param  Request  $request
     * @param  Search  $searchService
     *
     * @return JsonResponse
     * @throws Exception
     * @throws TypesenseClientError
     */
    #[Route('/search', name: 'search')]
    public function getContents(Request $request, Search $searchService): JsonResponse
    {
        $searchQuery = $request->query->get('q');
        $searchResults = $searchService->search($searchQuery);

        return $this->json([
            'data' => $searchResults,
        ]);
    }
}
