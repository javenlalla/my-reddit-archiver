<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SyncErrorsController extends AbstractController
{
    /**
     * View all sync errors.
     *
     * @return Response
     */
    #[Route('/sync-errors', name: 'sync_errors')]
    public function viewSyncErrors(): Response
    {
        return $this->render('sync-errors/view.html.twig');
    }
}
