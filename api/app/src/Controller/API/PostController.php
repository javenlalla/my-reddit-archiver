<?php

namespace App\Controller\API;

use App\Service\Reddit\Manager\Posts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class PostController extends AbstractController
{
    #[Route('/posts', name: 'posts')]
    public function getPosts(Posts $postsManager)
    {
        $posts = $postsManager->getPosts();

        return $this->json([
            'data' => [
                [
                    'redditId' => $posts[0]->getRedditId(),
                ]
            ]
        ]);
    }
}