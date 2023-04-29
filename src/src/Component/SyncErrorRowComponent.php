<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\SyncErrorLog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('sync_error_row', template: 'components/sync-errors-management/sync_error_row.html.twig')]
class SyncErrorRowComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public SyncErrorLog $errorLog;

    #[LiveAction]
    public function resync()
    {
        // @TODO: Implement logic.
    }

    #[LiveAction]
    public function delete()
    {
        // @TODO: Implement logic.
    }
}
