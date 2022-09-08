<?php

namespace App\Controller\API;

use App\Serializer\PostNormalizer;
use App\Service\Reddit\Manager\Posts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Serializer;

#[Route('/api', name: 'api_')]
class PostController extends AbstractController
{
    /**
     * @param  Posts  $postsManager
     *
     * @return JsonResponse
     * @throws ExceptionInterface
     */
    #[Route('/posts', name: 'posts')]
    public function getPosts(Posts $postsManager): JsonResponse
    {
        $posts = $postsManager->getPosts();

        $serializer = new Serializer([new PostNormalizer()], []);
        $responseData = $serializer->normalize($posts);

        return $this->json([
            'data' => $responseData,
        ]);
    }
}
