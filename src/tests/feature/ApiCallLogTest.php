<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Entity\ApiCallLog;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager\BatchSync;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiCallLogTest extends KernelTestCase
{
    private BatchSync $batchSyncManager;

    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->batchSyncManager = $container->get(BatchSync::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * Verify Tags can be associated to Contents and Contents can be retrieved
     * via their Tags.
     *
     * @return void
     */
    public function testApiCallLogging(): void
    {
        $targetRedditIds = [
            't3_vepbt0',
            't3_won0ky',
        ];

        $context = new Context('ApiCallLogTest:testApiCallLogging');
        $contents = $this->batchSyncManager->batchSyncContentsByRedditIds($context, $targetRedditIds);

        foreach ($targetRedditIds as $targetRedditId) {
            $searchString = '%' . $targetRedditId . '%';

            $query = $this->entityManager->createQuery(
                'SELECT al
            FROM App\Entity\ApiCallLog al
            WHERE al.endpoint LIKE :targetRedditId'
            )->setParameter('targetRedditId', $searchString);

            $result = $query->setMaxResults(1)->getResult();

            $this->assertCount(1, $result);
            $this->assertInstanceOf(ApiCallLog::class, $result[0]);
        }
    }
}
