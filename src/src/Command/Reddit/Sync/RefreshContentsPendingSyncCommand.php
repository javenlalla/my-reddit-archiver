<?php
declare(strict_types=1);

namespace App\Command\Reddit\Sync;

use App\Service\Reddit\Manager\BatchSync;
use App\Service\Reddit\Manager\SavedContents;
use App\Service\Reddit\SyncScheduler;
use App\Trait\Debug\MemoryUsageTrait;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'reddit:sync:refresh-pending',
    description: 'Refresh the local list of Contents pending sync by comparing to the latest list of Content results on Reddit\'s side.',
    hidden: false
)]
class RefreshContentsPendingSyncCommand extends Command
{
    use MemoryUsageTrait;

    public function __construct(
        private readonly BatchSync $batchSyncManager,
        private readonly SavedContents $savedContentsManager,
        private readonly SyncScheduler $syncScheduler,
    ) {
        parent::__construct();
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     *
     * @return int
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->savedContentsManager->refreshAllPendingEntities();

        $contentsPendingSync = $this->savedContentsManager->getContentsPendingSync(-1);
        $output->writeln([sprintf('%d total Contents pending sync.', count($contentsPendingSync))]);

        if ($output->isVerbose()) {
            $this->reportMemoryUsage($output);
        }

        return Command::SUCCESS;
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
}
