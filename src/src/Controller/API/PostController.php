<?php

namespace App\Controller\API;

use App\Serializer\PostNormalizer;
use App\Service\Reddit\Manager\Posts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class PostController extends AbstractController
{
    /**
     * @param  Posts  $postsManager
     * @param  PostNormalizer  $postNormalizer
     *
     * @return JsonResponse
     */
    #[Route('/posts', name: 'posts')]
    public function getPosts(Posts $postsManager, PostNormalizer $postNormalizer): JsonResponse
    {
        $posts = $postsManager->getPosts();

        // $serializer = new Serializer([new PostNormalizer()], []);
        // $responseData = $serializer->normalize($posts);
        $normalizedPosts = [];
        foreach ($posts as $post) {
            $normalizedPosts[] = $postNormalizer->normalize($post);
        }

        return $this->json([
            'data' => $normalizedPosts,
        ]);
    }
}
