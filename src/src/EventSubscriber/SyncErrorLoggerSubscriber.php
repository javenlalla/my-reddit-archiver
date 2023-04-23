<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AssetErrorLog;
use App\Entity\SyncErrorLog;
use App\Event\SyncErrorEvent;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event Subscriber to handle and log errors and Exceptions related to syncing
 * Content and downloading Assets.
 */
class SyncErrorLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SyncErrorEvent::NAME => 'onSyncError',
        ];
    }

    /**
     * Log an error related to syncing or downloading and execute any
     * post-logging logic based on the event's sync type.
     *
     * @param  SyncErrorEvent  $event
     *
     * @return void
     */
    public function onSyncError(SyncErrorEvent $event): void
    {
        $this->logger->error($event->getException());

        $errorLogEntity = null;
        switch ($event->getSyncType()) {
            case SyncErrorEvent::TYPE_CONTENT:
                $errorLogEntity = $this->handleContentSyncError($event);
                break;

            case SyncErrorEvent::TYPE_ASSET:
                $errorLogEntity = $this->handleAssetSyncError($event);
                break;
        }

        if (!empty($errorLogEntity)) {
            $this->em->persist($errorLogEntity);
            $this->em->flush();
        }
    }

    /**
     * Process and handle the Exception event in the case of an error occurring
     * with syncing/downloading a Reddit Content.
     *
     * @param  SyncErrorEvent  $event
     *
     * @return object
     */
    private function handleContentSyncError(SyncErrorEvent $event): object
    {
        $exception = $event->getException();
        $itemsInfo = $event->getAdditionalData()['itemsInfo'];

        $errorLog = new SyncErrorLog();

        $errorLog->setError($exception->getMessage());
        $errorLog->setErrorTrace($exception->getTraceAsString());
        $errorLog->setContentJson(json_encode($itemsInfo));
        $errorLog->setCreatedAt(new DateTimeImmutable());

        return $errorLog;
    }

    /**
     * Process and handle the Exception event in the case of an error occurring
     * with syncing/downloading an Asset.
     *
     * @param  SyncErrorEvent  $event
     *
     * @return object
     */
    private function handleAssetSyncError(SyncErrorEvent $event): object
    {
        $exception = $event->getException();
        $asset = $event->getAdditionalData()['asset'];

        $errorLog = new AssetErrorLog();
        $errorLog->setAsset($asset);

        $errorLog->setError($exception->getMessage());
        $errorLog->setErrorTrace($exception->getTraceAsString());
        $errorLog->setCreatedAt(new DateTimeImmutable());

        return $errorLog;
    }
}
