<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Entity\Content;
use App\Entity\ItemJson;
use App\Entity\Kind;
use App\Event\SyncErrorEvent;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Items;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BatchSync
{
    public function __construct(
        private readonly Items $itemsService,
        private readonly Contents $contentsManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Execute a sync for each ID in the provided array of Reddit IDs.
     *
     * @param  array  $redditIds  Reddit IDs formatted with kind.
     *                           Example: [t3_vepbt0, t5_2sdu8, t1_ia1smh6]
     *
     * @return Content[]
     * @throws InvalidArgumentException
     */
    public function batchSyncContentsByRedditIds(Context $context, array $redditIds): array
    {
        $contents = [];
        $itemJsons = $this->itemsService->getItemInfoByRedditIds($context, $redditIds);
        $itemsCount = count($itemJsons);
        $parentItemsInfo = $this->searchAndSyncParentIdsFromItemsInfo($context, $itemJsons);

        $processed = 0;
        $this->logger->info(sprintf('Batch syncing %d Reddit items.', count($itemJsons)));
        foreach ($itemJsons as $itemJson) {
            try {
                $content = $this->getContentFromItemInfo($context, $itemJson, $parentItemsInfo);
                $this->entityManager->persist($content);
                // Including the flush here may seem intensive, but it is
                // intentional to avoid unique clause violations upon inserts into
                // the database when the same Post is included more than once.
                $this->entityManager->flush();

                $contents[] = $content;
            } catch (Exception $e) {
                $this->handleSyncError($e, $itemJson);
            }

            $processed++;
            if (($processed % 10) === 0) {
                $this->logger->info(sprintf('Processed %d out of %d Reddit items.', $processed, $itemsCount));
            }
        }

        $this->logger->info('Completed batch sync.');

        return $contents;
    }

    /**
     * Given the provided array of items retrieved by the Reddit Info endpoint,
     * loop through and generate a separate array of parent items for any
     * original item that may be a Comment Content and, thus, contain a parent
     * `link_id`.
     *
     * @param  Context  $context
     * @param  ItemJson[]  $itemsJsons
     *
     * @return array  The parent items found, if any, structured as:
     *                  [
     *                    parentRedditId => [
     *                        ...parentItemInfo
     *                    ]
     *                  ]
     */
    public function searchAndSyncParentIdsFromItemsInfo(Context $context, array $itemsJsons): array
    {
        $parentRedditIds = [];
        foreach ($itemsJsons as $itemJson) {
            $itemInfo = $itemJson->getJsonBodyArray();

            if ($itemInfo['kind'] === Kind::KIND_COMMENT) {
                $parentRedditIds[] = $itemInfo['data']['link_id'];
            }
        }

        $parentItemsJsons = [];
        if (!empty($parentRedditIds)) {
            $parentItemsInfoUnsorted = $this->itemsService->getItemInfoByRedditIds($context, $parentRedditIds);

            foreach ($parentItemsInfoUnsorted as $parentItemJson) {
                $parentItemsJsons[$parentItemJson->getRedditId()] = $parentItemJson;
            }
        }

        return $parentItemsJsons;
    }

    /**
     * Denormalize and return a Content Entity based on the provided Reddit
     * item data retrieved from Info endpoint.
     *
     * @param  Context  $context
     * @param  ItemJson  $itemJson
     * @param  ItemJson[]  $parentItemJsons
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    private function getContentFromItemInfo(Context $context, ItemJson $itemJson, array $parentItemJsons = []): Content
    {
        $itemInfo = $itemJson->getJsonBodyArray();

        if ($itemInfo['kind'] === Kind::KIND_COMMENT) {
            $parentItemJson = $parentItemJsons[$itemInfo['data']['link_id']];

            $content = $this->contentsManager->parseAndDenormalizeContent($context, $parentItemJson->getJsonBodyArray(), ['commentData' => $itemInfo['data']]);
        } else {
            $content = $this->contentsManager->parseAndDenormalizeContent($context, $itemInfo);
        }

        return $content;
    }

    /**
     * Dispatch an error event to handle the provided Content sync Exception.
     *
     * @param  Exception  $e
     * @param  array  $itemsInfo
     *
     * @return void
     */
    private function handleSyncError(Exception $e, array $itemsInfo): void
    {
        $this->eventDispatcher->dispatch(
            new SyncErrorEvent(
                $e,
                SyncErrorEvent::TYPE_CONTENT,
                [
                    'itemsInfo' => $itemsInfo,
                ]
            ),
            SyncErrorEvent::NAME,
        );
    }
}
