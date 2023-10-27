<?php
declare(strict_types=1);

namespace App\Command\Reddit\Sync;

use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\ProfileContentGroup;
use App\Entity\SyncErrorLog;
use App\Event\SyncErrorEvent;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager\BatchSync;
use App\Service\Reddit\Manager\Contents;
use App\Service\Reddit\Manager\SavedContents;
use App\Service\Reddit\SyncScheduler;
use App\Trait\Debug\MemoryUsageTrait;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'reddit:sync',
    description: 'Sync all Contents from the Reddit profile down to local.',
    hidden: false
)]
class SyncCommand extends Command
{
    use MemoryUsageTrait;

    const PROFILE_GROUP_ALL = 'all';

    const DEFAULT_LIMIT = -1;

    public function __construct(
        private readonly BatchSync $batchSyncManager,
        private readonly SavedContents $savedContentsManager,
        private readonly SyncScheduler $syncScheduler,
        private readonly Contents $contentsManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption(
            name: 'group',
            mode: InputOption::VALUE_OPTIONAL,
            description: sprintf('The target Profile group to sync. Options: [all, %s, %s, %s, %s, %s, %s]. Default: all',
                ProfileContentGroup::PROFILE_GROUP_SAVED,
                ProfileContentGroup::PROFILE_GROUP_COMMENTS,
                ProfileContentGroup::PROFILE_GROUP_UPVOTED,
                ProfileContentGroup::PROFILE_GROUP_DOWNVOTED,
                ProfileContentGroup::PROFILE_GROUP_SUBMITTED,
                ProfileContentGroup::PROFILE_GROUP_GILDED,
            ),
            default: self::PROFILE_GROUP_ALL,
        );

        $this->addOption(
            name: 'refresh-pending',
            mode: InputOption::VALUE_NONE,
            description: 'Flag to refresh the list of Contents pending sync by comparing to the Contents on Reddit\'s side.',
        );

        $this->addOption(
            name: 'limit',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Limit the number of Contents to sync down from Reddit.',
        );
    }

    /**
     * Entry function to begin syncing the Reddit profile's `Saved` Content to
     * local.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     *
     * @return int
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $group = $this->getTargetGroup($input);
        if ($group === null) {
            $output->writeln('<error>Invalid Group specified.</error>');

            return Command::FAILURE;
        }

        $context = new Context('SyncCommand:execute');
        $time = time();

        $this->refreshPendingContents($context, $output, $group, $time);
        $pendingContents = $this->getContentsPendingSync($context, $input, $output, $group);

        $output->writeln('<info>Syncing pending Content</info>');
        $count = 0;
        $time = time();
        foreach ($pendingContents as $pendingContent) {
            try {
                $contentRawData = json_decode($pendingContent->getJsonData(), true);

                if ($contentRawData['kind'] === Kind::KIND_COMMENT) {
                    if (empty($pendingContent->getParentJsonData())) {
                        $errorLog = new SyncErrorLog();
                        $errorLog->setError(sprintf('No parent data found for Reddit ID: %s', $pendingContent->getFullRedditId()));
                        $errorLog->setCreatedAt(new DateTimeImmutable());
                        $this->entityManager->persist($errorLog);

                        continue;
                    } else {
                        $parentRawData = json_decode($pendingContent->getParentJsonData(), true);
                        $content = $this->contentsManager->parseAndDenormalizeContent($context, $parentRawData, ['commentData' => $contentRawData['data']]);
                    }
                } else {
                    $content = $this->contentsManager->parseAndDenormalizeContent($context, $contentRawData);
                }

                if ($content instanceof Content) {
                    $this->entityManager->persist($content);
                    $this->entityManager->remove($pendingContent);
                }
            } catch (Exception $e) {
                $this->handleSyncError($e, $contentRawData);
            }

            // This is a bit excessive but intentional to avoid unique
            // constraint violations when the same Post appears twice for
            // syncing (Ex: multiple Comments saved under the same Post).
            $this->entityManager->flush();

            $count++;
            if (($count % 10) === 0) {
                $output->writeln(sprintf('<info>Synced %d pending Contents in %d seconds.</info>', $count, (time() - $time)));
            }
        }

        $output->writeln(sprintf('<info>Completed sync of %d pending Content in %d seconds.</info>', $count, (time() - $time)));

        if ($output->isVerbose()) {
            $this->reportMemoryUsage($output);
        }

        $this->runSearchIndexing($output);

        return Command::SUCCESS;
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

    /**
     * Get the Group specified in the current execution.
     *
     * @param  InputInterface  $input
     *
     * @return string|null
     */
    private function getTargetGroup(InputInterface $input): ?string
    {
        $group = $input->getOption('group');

        return match ($group) {
            ProfileContentGroup::PROFILE_GROUP_SAVED,
            ProfileContentGroup::PROFILE_GROUP_COMMENTS,
            ProfileContentGroup::PROFILE_GROUP_UPVOTED,
            ProfileContentGroup::PROFILE_GROUP_DOWNVOTED,
            ProfileContentGroup::PROFILE_GROUP_SUBMITTED,
            ProfileContentGroup::PROFILE_GROUP_GILDED,
            self::PROFILE_GROUP_ALL
                => $group,
            default => null,
        };
    }

    /**
     * @param  OutputInterface  $output
     *
     * @return void
     */
    private function reportMemoryUsage(OutputInterface $output)
    {
        $output->writeln(
            'Memory used: ' . $this->getFormattedMemoryUsage()
        );
    }

    /**
     * Index all synced Contents for Search.
     *
     * @param  OutputInterface  $output
     *
     * @return void
     * @throws Exception
     */
    private function runSearchIndexing(OutputInterface $output)
    {
        $command = $this->getApplication()->find('reddit:search:index');

        $command->run(new ArrayInput([]), $output);
    }

    /**
     * Pull the latest list of Contents from the user's profile under the
     * specified group.
     *
     * @param  Context  $context
     * @param  OutputInterface  $output
     * @param  string  $group
     * @param  int  $time
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function refreshPendingContents(
        Context $context,
        OutputInterface $output,
        string $group,
        int $time
    ) {
        if ($group !== self::PROFILE_GROUP_ALL) {
            $output->writeln('<info>Updating list of Contents pending sync.</info>');
            $this->savedContentsManager->refreshPendingEntitiesByProfileGroup($context, $group);
        } else {
            $output->writeln(sprintf('<info>Updating list of %s Contents pending sync.</info>', ucfirst($group)));
            $this->savedContentsManager->refreshAllPendingEntities($context);
        }
        $output->writeln(sprintf('<info>Updated pending list in %d seconds.</info>', (time() - $time)));
    }

    /**
     * Retrieve the Contents that have been pulled from Reddit but have not yet
     * been synced.
     *
     * @param  Context  $context
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @param  string  $group
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function getContentsPendingSync(
        Context $context,
        InputInterface $input,
        OutputInterface $output,
        string $group
    ): array {
        $limit = $input->getOption('limit');
        if (empty($limit) || !is_int($limit)) {
            $limit = self::DEFAULT_LIMIT;
        }

        if ($group !== self::PROFILE_GROUP_ALL) {
            $pendingContents = $this->savedContentsManager->getContentsPendingSync($context, $group, $limit);
        } else {
            $pendingContents = $this->savedContentsManager->getContentsPendingSync($context, limit: $limit);
        }
        $output->writeln(sprintf('<info>Retrieved %d pending Contents to sync.</info>', count($pendingContents)));

        return $pendingContents;
    }
}
