<?php
declare(strict_types=1);

namespace App\Service\Reddit;

use App\Entity\Content;
use App\Service\Reddit\Manager\Comments as CommentsManager;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\InvalidArgumentException;

class SyncScheduler
{
    private const ONE_MINUTE_TIME_INTERVAL = 'PT1M';

    public function __construct(private readonly EntityManagerInterface $em, private readonly CommentsManager $commentsManager)
    {
    }

    /**
     * Calculate the next Sync date based on the provided Content and if not
     * disabled, set the date on the Entity and persist it.
     *
     * @param  Content  $content
     *
     * @return void
     */
    public function calculateAndSetNextSyncByContent(Content $content): void
    {
        $syncDate = $this->calculateNextSyncByContent($content);
        if ($syncDate instanceof DateTimeImmutable) {
            $content->setNextSyncDate($syncDate);

            $this->em->persist($content);
            $this->em->flush();
        }
    }

    /**
     * Calculate the next Sync date based on the provided Content. If it is
     * detected that syncing should be disabled, return null.
     *
     * @param  Content  $content
     *
     * @return DateTimeImmutable|null
     * @throws InvalidArgumentException
     */
    public function calculateNextSyncByContent(Content $content): ?DateTimeImmutable
    {
        $post = $content->getPost();
        if ($post->isIsArchived() === true) {
            return null;
        }

        $currentDate = DateTimeImmutable::createFromFormat('U', (string) time())->format('Y-m-d');
        $contentPostCreatedDateTime = $content->getPost()->getCreatedAt();
        $contentPostCreatedDate = $content->getPost()->getCreatedAt()->format('Y-m-d');

        if ($currentDate === $contentPostCreatedDate) {
            return $contentPostCreatedDateTime->add(new DateInterval(self::ONE_MINUTE_TIME_INTERVAL));
        }

        $lastUpdatedDateTime = $contentPostCreatedDateTime;
        $latestComment = $this->commentsManager->getLatestCommentByContent($content);
        if ($latestComment instanceof \App\Entity\Comment) {
            $lastUpdatedDateTime = $latestComment->getLatestCommentAuthorText()->getCreatedAt();
        }

        return $this->calculateNextSyncByLastDate($lastUpdatedDateTime);
    }

    /**
     * Using the provided last updated DateTime, calculate the next Sync date,
     * if not disabled.
     *
     * @param  DateTimeImmutable  $lastUpdatedDateTime
     *
     * @return DateTimeImmutable|null
     * @throws Exception
     */
    public function calculateNextSyncByLastDate(DateTimeImmutable $lastUpdatedDateTime): ?DateTimeImmutable
    {
        $nextSyncDateSeconds = $this->calculateSecondsToNextSync((int) $lastUpdatedDateTime->format('U'));
        if ($nextSyncDateSeconds === 0) {
            // Disable the next sync.
            return null;
        }

        $currentDateTime = DateTimeImmutable::createFromFormat('U', (string) time());

        return $currentDateTime->add(new DateInterval(sprintf('PT%dS', $nextSyncDateSeconds)));
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
