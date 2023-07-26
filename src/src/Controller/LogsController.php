<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ApiCallLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LogsController extends AbstractController
{
    #[Route('/logs/api-calls', 'logs.api_calls')]
    public function viewApiCallLogs(ApiCallLogRepository $apiCallLogRepository): Response
    {
        $logs = $apiCallLogRepository->findBy([], ['createdAt' => 'DESC'], 100);

        return $this->render('logs/api_calls.html.twig', [
            'logs' => $logs,
        ]);
    }
}
