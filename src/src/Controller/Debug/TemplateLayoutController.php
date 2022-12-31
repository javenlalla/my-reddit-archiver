<?php
declare(strict_types=1);

namespace App\Controller\Debug;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class TemplateLayoutController extends AbstractController
{
    #[Route('/base', name: 'base')]
    public function getBaseTemplate(): Response
    {
        return $this->render('base.html.twig');
    }

    #[Route('/layout', name: 'layout')]
    public function getLayout(): Response
    {
        return $this->render('layout.html.twig');
    }

    #[Route('/search-results', name: 'search-results')]
    public function getSearchResults(Request $request): Response
    {
        $posts = [
            [
                'id' => 1,
                'title' => 'First Post',
            ],
            [
                'id' => 2,
                'title' => 'Second Post',
            ],
            [
                'id' => 3,
                'title' => 'Third Post',
            ],
            [
                'id' => 4,
                'title' => 'Fourth Post',
            ],
        ];

        $filteredPosts = [];
        $query = $request->get('q', '');
        if (!empty($query)) {
            foreach ($posts as $post) {
                if (str_contains(strtolower($post['title']), $query)) {
                    $filteredPosts[] = $post;
                }
            }
        } else {
            $filteredPosts = $posts;
        }

        return $this->render('search-results.html.twig', [
            'posts' => $filteredPosts,
        ]);
    }
}