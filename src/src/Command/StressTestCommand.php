<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\Reddit\Api;
use App\Service\Reddit\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

    public function configure(): void
    {
        $this->addOption(
            name: 'limit',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'The maximum number of `Hot` Posts that should be retrieved and processed.',
            default: 25,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>-------------Reddit | Sync | Stress Test-------------</info>');

        $limit = (int) $input->getOption('limit');

        $output->writeln('<info>Retrieving currently Hot URLs from Reddit.</info>');
        $hotPostsUrls = $this->getHotPostsUrls($limit);
        $output->writeln(sprintf('<info>%d Hot URLs retrieved.</info>', count($hotPostsUrls)));

        $startTime = time();
        $synced = 0;
        try {
            foreach ($hotPostsUrls as $hotPostUrl) {
                $this->syncManager->syncContentByUrl($hotPostUrl);

                $synced++;
                if (($synced % 5) === 0) {
                    $output->writeln(sprintf('<info>%d seconds elapsed. %d URLs processed.</info>', (time() - $startTime), $synced));
                }
            }
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error occurred with URL %s: %s</error>', $hotPostUrl, $e->getMessage()));
            $output->writeln(sprintf('<error>%s</error>', $e->getTraceAsString()), OutputInterface::VERBOSITY_VERBOSE);

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Completed syncing with %d URLs processed in %d seconds.</info>', $synced, (time() - $startTime)));

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
    private function getHotPostsUrls(int $limit = 25): array
    {
        $urls = [];
        $hotPostsResponseData = $this->redditApi->getHotPosts($limit);

        foreach ($hotPostsResponseData['data']['children'] as $hotPostResponseData) {
            $urls[] = $this->extractTargetUrlFromResponseData($hotPostResponseData);
        }

        return $urls;
    }

    /**
     * Get the target URL from the provided Hot Post response data. The URL
     * will either be the Post's URL or a selected Comment (5th) in the Post
     * itself.
     *
     * @param  array{
     *     data: array{
     *          id: string,
     *          permalink: string,
     *      }
     *     }  $hotPostResponseData
     *
     * @return string
     */
    private function extractTargetUrlFromResponseData(array $hotPostResponseData): string
    {
        $postData = $hotPostResponseData['data'];
        $url = $postData['permalink'];

        if ($this->shouldTargetComment($postData['id'])) {
            $postJsonUrlResponseData = $this->redditApi->getPostFromJsonUrl($url);

            // Target the 5th Comment's URL.
            $url = $postJsonUrlResponseData[1]['data']['children'][5]['data']['permalink'];
        }

        return $url;
    }

    /**
     * This is a devised logic to help randomize when Comments should be targeted
     * instead of their parent Posts.
     *
     * Hash the provided Reddit ID and determine if the first integer in the
     * hashed value is even or odd.
     *
     * First integer detection based on solution provided here:
     * https://stackoverflow.com/a/15155296
     *
     * @param  string  $redditId
     *
     * @return bool
     */
    private function shouldTargetComment(string $redditId): bool
    {
        $idHash = md5($redditId);
        $idIntegers = array_filter(preg_split("/\D+/", $idHash));
        $firstInteger = reset($idIntegers);

        if (($firstInteger % 2) === 0) {
            return true;
        }

        return false;
    }
}
