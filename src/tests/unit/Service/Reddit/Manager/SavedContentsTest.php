<?php
declare(strict_types=1);

namespace App\Tests\unit\Service\Reddit\Manager;

use App\Entity\Content;
use App\Entity\ContentPendingSync;
use App\Entity\ProfileContentGroup;
use App\Service\Reddit\Manager\BatchSync;
use App\Service\Reddit\Manager\SavedContents;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SavedContentsTest extends KernelTestCase
{
    private SavedContents $savedContentsManager;

    private BatchSync $batchSyncManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->savedContentsManager = $container->get(SavedContents::class);
        $this->batchSyncManager = $container->get(BatchSync::class);
    }

    /**
     * Verify a Contents can be retrieved and inserted into the pending sync
     * table until actual sync is executed.
     *
     * @return void
     */
    public function testPendingSyncPersistence(): void
    {
        $contentsPendingSync = $this->savedContentsManager->getContentsPendingSync(ProfileContentGroup::PROFILE_GROUP_SAVED, 10);
        $this->assertEmpty($contentsPendingSync);

        $contentsPendingSync = $this->savedContentsManager->getContentsPendingSync(ProfileContentGroup::PROFILE_GROUP_SAVED, 10, true);
        $this->assertCount(10, $contentsPendingSync);
        $this->assertInstanceOf(ContentPendingSync::class, $contentsPendingSync[0]);

        $contentPendingSync = $contentsPendingSync[0];
        $syncedContents = $this->batchSyncManager->batchSyncContentsByRedditIds([$contentPendingSync->getFullRedditId()]);

        $this->assertInstanceOf(Content::class, $syncedContents[0]);
        $this->assertEquals($contentPendingSync->getFullRedditId(), $syncedContents[0]->getFullRedditId());
    }
}
