<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\SyncErrorLog;
use App\Repository\SyncErrorLogRepository;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/sync-errors', name: 'sync_errors_')]
class SyncErrorsController extends AbstractController
{
    /**
     * View all sync errors.
     *
     * @return Response
     */
    #[Route('/', name: 'index')]
    public function viewSyncErrors(SyncErrorLogRepository $syncErrorLogRepository): Response
    {
        return $this->render('sync-errors/view.html.twig', [
            'errorLogs' => $syncErrorLogRepository->findAll(),
        ]);
    }

    /**
     * Attempt to re-sync a Content based on the specified Sync Error.
     *
     * @param  SyncErrorLog  $syncErrorLog
     *
     * @return Response
     */
    #[Route('/{id}/resync', name: 'resync', methods: ['POST'])]
    public function resync(Manager $manager, SyncErrorLogRepository $syncErrorLogRepository, SyncErrorLog $syncErrorLog): Response
    {
        try {
            $url = $syncErrorLog->getUrl();
            if (empty($url)) {
                $contentJson = json_decode($syncErrorLog->getContentJson(), true);

                $url = $contentJson['data']['permalink'];
            }

            $content = $manager->syncContentByUrl(
                new Context(Context::SOURCE_SYNC_ERROR_RESYNC),
                $url,
            );

            // Content was synced successfully, therefore the Sync Error Log
            // can be deleted.
            $syncErrorLogRepository->remove($syncErrorLog, true);
        } catch (Exception $e) {
            return $this->render('sync-errors/resync_error.html.twig', [
                'errorLog' => $syncErrorLog,
                'resyncError' => $e,
            ], new Response(null, 500));
        }

        return new Response();
    }

    /**
     * Delete a Sync Error Log.
     *
     * @param  SyncErrorLog  $syncErrorLog
     *
     * @return Response
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(SyncErrorLogRepository $syncErrorLogRepository, SyncErrorLog $syncErrorLog): Response
    {
        try {
            $syncErrorLogRepository->remove($syncErrorLog, true);
        } catch (Exception $e) {
            return new Response($e->getMessage(), 500);
        }

        return new Response();
    }
}
