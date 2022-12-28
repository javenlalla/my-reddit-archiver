<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\Reddit\Api;
use App\Service\Reddit\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'reddit:sync:stress-test',
    description: 'Execute syncing of currently "Hot" Posts to detect errors or bugs during syncing.',
)]
class StressTestCommand extends Command
{
    public function __construct(private readonly Manager $syncManager, private readonly Api $redditApi)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>-------------Reddit | Sync | Stress Test-------------</info>');
        $hotPostsUrls = $this->getHotPostsUrls();

        try {
            foreach ($hotPostsUrls as $hotPostUrl) {
                $this->syncManager->syncContentByUrl($hotPostUrl);
            }
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error occurred with URL %s: %s</error>', $hotPostUrl, $e->getMessage()));
            $output->writeln(sprintf('<error>%s</error>', $e->getTraceAsString()), OutputInterface::VERBOSITY_VERBOSE);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Retrieve the current listing of "Hot" Contents URLs.
     *
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function getHotPostsUrls(): array
    {
        $urls = [];
        $hotPostsResponseData = $this->redditApi->getHotPosts();

        foreach ($hotPostsResponseData['data']['children'] as $hotPostResponseData) {
            $urls[] = $hotPostResponseData['data']['permalink'];
        }

        return $urls;
    }
}
