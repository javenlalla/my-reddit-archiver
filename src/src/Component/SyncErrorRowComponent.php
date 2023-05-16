<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\SyncErrorLog;
use App\Service\Reddit\Manager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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

    #[LiveProp(writable: true)]
    public string $reSyncError = '';

    public function __construct(
        private readonly Manager $manager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Attempt a re-sync of the Content associated to this Sync Error Log.
     *
     * @return void
     */
    #[LiveAction]
    public function resync(): void
    {
        // Clear any existing re-sync error prior to re-sync attempt.
        $this->reSyncError = '';

        try {
            $url = $this->errorLog->getUrl();
            if (empty($url)) {
                $contentJson = json_decode($this->errorLog->getContentJson(), true);

                $url = $contentJson['data']['permalink'];
            }

            $content = $this->manager->syncContentByUrl($url);
            // Content was synced successfully, therefore the Sync Error Log
            // can be deleted.
            $this->delete();
        } catch (Exception $e) {
            $this->reSyncError = $e->getMessage();
        }
    }

    /**
     * Dispatch the event to delete this Sync Error.
     *
     * @return void
     */
    #[LiveAction]
    public function delete(): void
    {
        // @TODO: Implement delete logic.
    }


}
