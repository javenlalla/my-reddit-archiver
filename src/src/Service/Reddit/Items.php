<?php
declare(strict_types=1);

namespace App\Service\Reddit;

use App\Entity\ItemJson;
use App\Repository\ItemJsonRepository;
use App\Service\Reddit\Api\Context;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class Items
{
    public function __construct(
        private readonly ItemJsonRepository $itemJsonRepository,
        private readonly Api $redditApi,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Retrieve an Item JSON Entity by the provided Reddit ID. If no Entity is
     * found, execute an API call to retrieve and persist the Item Info.
     *
     * @param  Context  $context
     * @param  string  $redditId
     *
     * @return ItemJson
     */
    public function getItemInfoByRedditId(Context $context, string $redditId): ItemJson
    {
        $itemJson = $this->itemJsonRepository->findByRedditId($redditId);
        if (!$itemJson instanceof ItemJson) {
            return $this->getApiItemInfoByRedditId($context, $redditId);
        }

        return $itemJson;
    }

    /**
     * Retrieve the Item JSON Entities by the provided array of Reddit IDs.
     * For the IDs not found locally, execute the batch API call to retrieve the
     * missing Item Infos and persist them.
     *
     * @param  Context  $context
     * @param  array  $redditIds
     *
     * @return ItemJson[]
     */
    public function getItemInfoByRedditIds(Context $context, array $redditIds): array
    {
        $foundRedditIds = [];
        $itemJsons = $this->itemJsonRepository->findByRedditIds($redditIds);
        foreach ($itemJsons as $itemJson) {
            $foundRedditIds[] = $itemJson->getRedditId();
        }

        $notFoundRedditIds = array_diff($redditIds, $foundRedditIds);
        $this->pullItemInfosFromApi($context, $notFoundRedditIds);

        return $this->itemJsonRepository->findByRedditIds($redditIds);
    }

    /**
     * Execute an API call to retrieve a Reddit Item Info by its ID. Once
     * retrieved, persist the Info as an Item JSON Entity and return the Entity.
     *
     * @param  Context  $context
     * @param  string  $redditId
     *
     * @return ItemJson
     */
    public function getApiItemInfoByRedditId(Context $context, string $redditId): ItemJson
    {
        $itemInfo = $this->redditApi->getRedditItemInfoById($context, $redditId);

        return $this->persistAndReturnItemJson($redditId, $itemInfo);
    }

    /**
     * Persist the provided Item Info associated to the targeted Reddit ID and
     * return the Item JSON Entity.
     *
     * @param  string  $redditId
     * @param  array  $itemInfo
     *
     * @return ItemJson
     */
    private function persistAndReturnItemJson(string $redditId, array $itemInfo): ItemJson
    {
        $itemJson = $this->itemJsonRepository->findByRedditId($redditId);
        if (!$itemJson instanceof ItemJson) {
            $itemJson = new ItemJson();
            $itemJson->setRedditId($redditId);
            $itemJson->setJsonBody(json_encode($itemInfo));

            $this->itemJsonRepository->add($itemJson, true);
        }

        return $itemJson;
    }

    /**
     * Navigate the provided Item Info array and retrieve its Reddit ID.
     *
     * @param  array  $retrievedInfo
     *
     * @return string
     * @throws Exception
     */
    private function getRedditIdFromInfo(array $retrievedInfo): string
    {
        if (!empty($retrievedInfo['data']['name'])) {
            return $retrievedInfo['data']['name'];
        }

        throw new Exception(sprintf(
            'No Reddit ID found in Info Body: %s',
            var_export($retrievedInfo, true))
        );
    }

    /**
     * Retrieve the Infos for the provided array of Reddit IDs and persist them.
     *
     * @param  Context  $context
     * @param  array  $redditIds
     *
     * @return void
     */
    private function pullItemInfosFromApi(Context $context, array $redditIds): void
    {
        $retrievedInfos = $this->redditApi->getRedditItemInfoByIds($context, $redditIds);

        $batchSize = 100;
        $batchCount = 0;
        foreach ($retrievedInfos as $retrievedInfo) {
            $redditId = $this->getRedditIdFromInfo($retrievedInfo);

            $itemJson = new ItemJson();
            $itemJson->setRedditId($redditId);
            $itemJson->setJsonBody(json_encode($retrievedInfo));

            $this->entityManager->persist($itemJson);
            $batchCount++;

            if (($batchCount % $batchSize) === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
    }
}
