<?php
declare(strict_types=1);

namespace App\Component;

use App\Repository\SyncErrorLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('sync_errors_management')]
class SyncErrorsManagementComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(private readonly SyncErrorLogRepository $errorLogRepository)
    {
    }

    public function getErrorLogs(): array
    {
        return $this->errorLogRepository->findAll();
    }
}
