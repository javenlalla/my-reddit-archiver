<?php
declare(strict_types=1);

namespace App\Command;

use App\Repository\ContentRepository;
use App\Service\Typesense\Search;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'reddit:search:index',
    description: 'Index on Contents for Search.',
)]
class IndexContentsCommand extends Command
{
    public function __construct(private readonly ContentRepository $contentRepository, private readonly Search $tsSearch)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>-------------Reddit | Search | Index Contents-------------</info>');

        $contents = $this->contentRepository->findAll();

        $output->writeln(sprintf('<info>Indexing %d Contents.</info>', count($contents)));
        $indexedCount = 0;
        foreach ($contents as $content) {
            $this->tsSearch->indexContent($content);

            $indexedCount++;
            if (($indexedCount % 5) === 0) {
                $output->writeln(sprintf('<info>Indexed %d Contents.</info>', $indexedCount));
            }
        }

        $output->writeln(sprintf('<info>Completed indexing of %d Contents.</info>', $indexedCount));

        return Command::SUCCESS;
    }
}
