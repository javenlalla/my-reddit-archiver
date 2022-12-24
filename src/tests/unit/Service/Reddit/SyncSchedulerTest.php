<?php
declare(strict_types=1);

namespace App\Tests\Service\Reddit;

use App\Service\Reddit\SyncScheduler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SyncSchedulerTest extends KernelTestCase
{
    private SyncScheduler $syncScheduler;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->syncScheduler = $container->get(SyncScheduler::class);
    }

    /**
     * Verify the expected seconds to next Sync dates based on the provided
     * time passed.
     *
     * @dataProvider getCalculateNextDateData()
     *
     * @param  int  $secondsPassed
     * @param  int  $expectedNextDateSeconds
     *
     * @return void
     */
    public function testCalculateNextDate(int $secondsPassed, int $expectedNextDateSeconds): void
    {
        $currentTimestamp = time();
        $pastTimestamp = $currentTimestamp - $secondsPassed;

        $nextSyncDateSeconds = $this->syncScheduler->calculateSecondsToNextSync($pastTimestamp);
        $this->assertEquals($expectedNextDateSeconds, $nextSyncDateSeconds);
    }

    /**
     * Data Provider for date parameters required for calculating next sync
     * dates.
     *
     * @return array
     */
    public function getCalculateNextDateData(): array
    {
        return [
            'Less Than 2 Hours -> 60 Seconds' => [
                'secondsPassed' => 30,
                'expectedNextDateSeconds' => 60,
            ],
            'Between 2 And 12 Hours -> 30 Minutes' => [
                'secondsPassed' => 43000,
                'expectedNextDateSeconds' => 1800,
            ],
            'Between 12 And 48 Hours -> 1 Hour' => [
                'secondsPassed' => 171800,
                'expectedNextDateSeconds' => 3600,
            ],
            'Between 48 Hours And 1 Week -> 2 Hours' => [
                'secondsPassed' => 603800,
                'expectedNextDateSeconds' => 7200,
            ],
            'Between 1 Week And 30 Days -> 1 Day' => [
                'secondsPassed' => 2591000,
                'expectedNextDateSeconds' => 86400,
            ],
            'Between 30 Days And 180 Days -> 3 Days' => [
                'secondsPassed' => 15542000,
                'expectedNextDateSeconds' => 259200,
            ],
            'More Than 180 Days -> Disable' => [
                'secondsPassed' => 15562000,
                'expectedNextDateSeconds' => 0,
            ],
        ];
    }
}
