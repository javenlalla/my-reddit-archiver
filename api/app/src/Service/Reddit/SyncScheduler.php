<?php
declare(strict_types=1);

namespace App\Service\Reddit;

use DateInterval;
use DateTimeImmutable;
use Exception;

class SyncScheduler
{
    /**
     * @param  DateTimeImmutable  $lastUpdatedDate
     *
     * @return DateTimeImmutable|null
     * @throws Exception
     */
    public function calculateNextSyncByLastDate(DateTimeImmutable $lastUpdatedDate): ?DateTimeImmutable
    {
        // @TODO: Check if archived or Content created same day.
        $nextSyncDateSeconds = $this->calculateSecondsToNextSync((int) $lastUpdatedDate->format('U'));
        if ($nextSyncDateSeconds === 0) {
            // Disable the next sync.
            return null;
        }

        return $lastUpdatedDate->add(new DateInterval(sprintf('PT%dS', $nextSyncDateSeconds)));
    }

    /**
     * Using the provided last updated timestamp, calculate the expected seconds
     * until the next Sync date.
     *
     * If the last updated timestamp is older than 180 days, return 0 to
     * indicate disabling the sync.
     *
     * @param  int  $lastUpdateTimestamp
     *
     * @return int
     */
    public function calculateSecondsToNextSync(int $lastUpdateTimestamp): int
    {
        $secondsPassed = time() - $lastUpdateTimestamp;

        return match (true) {
            // Less Than 2 Hours -> 60 Seconds
            $secondsPassed <= 7200 => 60,
            // Between 2 And 12 Hours -> 30 Minutes
            $secondsPassed <= 43200 => 1800,
            // Between 12 And 48 Hours -> 1 Hour
            $secondsPassed <= 172800 => 3600,
            // Between 48 Hours And 1 Week -> 2 Hours
            $secondsPassed <= 604800 => 7200,
            // Between 1 Week And 30 Days -> 1 Day
            $secondsPassed <= 2592000 => 86400,
            // Between 30 Days And 180 Days -> 3 Days
            $secondsPassed <= 15552000 => 259200,
            // More Than 180 Days -> Disable
            default => 0,
        };
    }
}
