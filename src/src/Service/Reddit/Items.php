<?php
declare(strict_types=1);

namespace App\Service\Reddit;

use App\Entity\ItemJson;
use App\Repository\ItemJsonRepository;
use App\Service\Reddit\Api\Context;

class Items
{
    public function __construct(
        private readonly ItemJsonRepository $itemJsonRepository,
        private readonly Api $redditApi
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
}
