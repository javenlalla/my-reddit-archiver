<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Entity\ItemJson;
use App\Repository\ItemJsonRepository;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Items;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ItemsTest extends KernelTestCase
{
    private Items $items;

    private ItemJsonRepository $itemJsonRepository;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->items = $container->get(Items::class);
        $this->itemJsonRepository = $container->get(ItemJsonRepository::class);
    }

    /**
     * Verify Reddit Item look-ups are persisted correctly and retrieved via API
     * when necessary.
     *
     * @return void
     */
    public function testItemJsonStorage(): void
    {
        $targetRedditIds = [
            't3_vepbt0',
            't3_won0ky',
        ];

        $context = new Context('ItemsTest:testItemJsonStorage');

        foreach ($targetRedditIds as $targetRedditId) {
            $itemJson = $this->itemJsonRepository->findOneBy(['redditId' => $targetRedditId]);
            $this->assertNull($itemJson);

            $itemJson = $this->items->getItemInfoByRedditId($context, $targetRedditId);
            $this->assertInstanceOf(ItemJson::class, $itemJson);

            // Verify the JSON body is now stored, associated to the Reddit ID.
            $itemJson = $this->itemJsonRepository->findOneBy(['redditId' => $targetRedditId]);
            $this->assertInstanceOf(ItemJson::class, $itemJson);
            $this->assertEquals($targetRedditId, $itemJson->getRedditId());
        }
    }
}
