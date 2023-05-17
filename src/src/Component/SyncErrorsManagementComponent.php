<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\SyncErrorLog;
use App\Repository\SyncErrorLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsLiveComponent('sync_errors_management')]
class SyncErrorsManagementComponent extends AbstractController
{
    use DefaultActionTrait;

    /**
     * @var $errorLogs SyncErrorLog[]
     */
    #[LiveProp(useSerializerForHydration: true)]
    public array $errorLogs = [];

    public function __construct(
        private readonly SyncErrorLogRepository $errorLogRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[PreMount]
    public function preMount(array $data): array
    {
        $data['errorLogs'] = $this->errorLogRepository->findAll();

        return $data;
    }
}
