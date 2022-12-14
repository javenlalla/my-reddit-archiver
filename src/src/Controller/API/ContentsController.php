<?php
declare(strict_types=1);

namespace App\Controller\API;

use App\Normalizer\ContentNormalizer;
use App\Repository\ContentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class ContentsController extends AbstractController
{
    /**
     * @param  ContentRepository  $contentRepository
     * @param  ContentNormalizer  $contentNormalizer
     *
     * @return JsonResponse
     */
    #[Route('/contents', name: 'contents')]
    public function getContents(ContentRepository $contentRepository, ContentNormalizer $contentNormalizer): JsonResponse
    {
        $contents = $contentRepository->findBy([], [], 50);
        $normalizedContents = [];

        foreach ($contents as $content) {
            $normalizedContents[] = $contentNormalizer->normalize($content);
        }

        return $this->json([
            'data' => $normalizedContents,
        ]);
    }
}
