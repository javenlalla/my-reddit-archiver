<?php
declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'search:clear-index',
    description: 'Clear the Search index by purging the Search Content table.',
)]
class ClearSearchIndexCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stmt = $this->entityManager->getConnection()->prepare('DELETE FROM search_content;');
        $stmt->executeStatement();

        return Command::SUCCESS;
    }
}
