<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Search;
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
}
