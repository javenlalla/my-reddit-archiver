<?php
declare(strict_types=1);

namespace App\Command\Reddit\Sync;

use App\Entity\Content;
use App\Repository\ContentRepository;
use App\Service\Reddit\Manager;
use App\Service\Reddit\SyncScheduler;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'reddit:sync:saved:full',
    description: 'Loop through your Reddit profile\'s Saved Contents and persist them locally. Note: this does not update already synced Contents; it only pulls down Contents not already saved locally.',
    hidden: false
)]
class SavedContentsCommand extends Command
{
    public function __construct(
        private readonly Manager $manager,
        private readonly Manager\SavedContents $savedContentsManager,
        private readonly ContentRepository $contentRepository,
        private readonly SyncScheduler $syncScheduler,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption(
            name: 'limit',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'The maximum number of `Saved` Content that should be retrieved and processed.',
            default: 100,
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
        $limit = (int) $input->getOption('limit');

        $output->writeln('<info>-------------Sync API-------------</info>');
        $output->writeln('<info>Sync Reddit profile `Saved` Contents to local.</info>');

        $savedContentsData = $this->savedContentsManager->getNonLocalSavedContentsData($limit);
        $output->writeln(sprintf('<comment>%d `Saved` Contents retrieved.</comment>', count($savedContentsData)));

        $result = $this->processedSavedContentsData($output, $savedContentsData);
        if ($result === Command::FAILURE) {
            return $result;
        }

        $output->writeln([
            '<comment>Sync completed.</comment>',
            sprintf('<comment>%d `Saved` Contents data synced to local.</comment>', count($savedContentsData))
        ]);

        return Command::SUCCESS;
    }

    /**
     * Loop through the provided array of `Saved` Contents Data and sync them
     * down to local.
     *
     * @param  OutputInterface  $output
     * @param  array  $savedContentsData
     *
     * @return int
     * @throws InvalidArgumentException
     */
    private function processedSavedContentsData(OutputInterface $output, array $savedContentsData): int
    {
        $count = 0;
        $output->writeln('<comment>Syncing `Saved` Contents to local.</comment>');

        foreach ($savedContentsData as $savedContentData) {
            try {
                $content = $this->manager->syncContentByUrl($savedContentData['data']['permalink']);

                if ($content->getPost()->isIsArchived() === false) {
                    $this->syncScheduler->calculateAndSetNextSyncByContent($content);
                }

                $count++;
                if (($count % 10) === 0) {
                    $output->writeln(sprintf('<comment>%d `Saved` Contents processed.</comment>', $count));
                }
            } catch (Exception $e) {
                $output->writeln(sprintf('<error>%s</error>', var_export($savedContentData ,true)), OutputInterface::VERBOSITY_VERBOSE);
                $output->writeln([
                    sprintf('<error>Error: %s', $e->getMessage()),
                    sprintf('<error>Content: %s: %s</error>', $savedContentData['kind'], $savedContentData['data']['permalink']),
                ]);

                $this->purgeAttemptedContent($savedContentData);

                if ($output->isVerbose()) {
                    $output->writeln(sprintf('<error>Stack Trace: %s', $e->getTraceAsString()));
                }

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Purge attempted Content to ensure no partial records or relations are
     * lingering in the database.
     *
     * @param  array  $savedContentData
     *
     * @return void
     */
    private function purgeAttemptedContent(array $savedContentData): void
    {
        $localContent = $this->savedContentsManager->getLocalContentFromSavedContentData($savedContentData);

        if ($localContent instanceof Content) {
            $this->contentRepository->remove($localContent, true);
        }
    }
}
