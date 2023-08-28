<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ApiCallLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class LogsController extends AbstractController
{
    #[Route('/logs/api-calls', 'logs.api_calls')]
    public function viewApiCallLogs(ApiCallLogRepository $apiCallLogRepository, ChartBuilderInterface $chartBuilder): Response
    {
        $callsByMinuteGroups = $apiCallLogRepository->findCallsGroupedByMinute();
        $dataset = [
            [
                'label' => 'Number Of Calls',
                'backgroundColor' => 'rgb(255, 99, 132)',
                'borderColor' => 'rgb(255, 99, 132)',
                'data' => [],
            ]
        ];

        $labels = [];
        $data = [];
        foreach ($callsByMinuteGroups as $group) {
            $labels[] = $group->minuteCalled;
            $data[] = $group->totalCalls;
        }

        $dataset[0]['data'] = $data;

        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => $labels,
            'datasets' => $dataset,
        ]);

        $chart->setOptions([
            'scales' => [
                'y' => [
                    'suggestedMin' => 0,
                    'suggestedMax' => 60,
                ],
            ],
        ]);

        $logs = $apiCallLogRepository->findBy([], ['createdAt' => 'DESC'], 100);

        return $this->render('logs/api_calls.html.twig', [
            'logs' => $logs,
            'chart' => $chart,
        ]);
    }
}
