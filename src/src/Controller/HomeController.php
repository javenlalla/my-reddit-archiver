<?php
declare(strict_types=1);

namespace App\Controller;

use App\Form\SearchForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        $searchForm = $this->createForm(SearchForm::class);

        return $this->render('home/home.html.twig', [
            'searchForm' => $searchForm->createView(),
        ]);
    }
}
