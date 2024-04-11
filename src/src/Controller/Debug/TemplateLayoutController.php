<?php
declare(strict_types=1);

namespace App\Controller\Debug;

use App\Form\SearchFormDeprecated;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        $searchForm = $this->createForm(SearchFormDeprecated::class);

        return $this->render('_sidenav.html.twig', [
            'searchForm' => $searchForm->createView(),
        ]);
    }
}
