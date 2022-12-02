<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Kind;
use App\Service\Reddit\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:sync-single-content',
    description: 'Sync a single Content to local by Reddit URL.',
    aliases: ['app:sync-single'],
)]
class SyncSingleContentCommand extends Command
{
    public function __construct(private readonly Manager $manager)
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption(
            name: 'url',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Content Reddit URL to sync. URL can be either a Link URL or a direct Comment URL.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetUrl = $input->getOption('url');

        $output->writeln('<info>-------------Sync Manager | Sync Single Content-------------</info>');
        $output->writeln('<info>Sync a single Reddit Content URL to local</info>');
        $output->writeln('');
        $output->writeln('<info>Beginning Sync.</info>');

        $content = $this->manager->syncContentByUrl($targetUrl);

        $output->writeln('<info>Sync completed.</info>');

        if ($content->getKind()->getRedditKindId() === Kind::KIND_COMMENT) {
            $output->writeln('<info>Comment URL synced to local.</info>');
        } else {
            $output->writeln(sprintf('<info>URL for Link Post "%s" synced to local.</info>', $content->getPost()->getTitle()));
        }

        return Command::SUCCESS;
    }
}
