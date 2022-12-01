<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Service\Reddit\Api;
use App\Service\Reddit\Manager;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'app:sync-saved-contents',
    description: 'Loop through your Reddit profile\'s Saved Contents and persist them locally',
    aliases: ['app:sync'],
    hidden: false
)]
class SyncSavedContentsCommand extends Command
{
    const DEFAULT_LIMIT = 100;

    const CACHE_KEY = 'saved-contents-command';

    public function __construct(
        private readonly Manager $manager,
        private readonly Api $redditApi,
        private readonly PostRepository $postRepository,
        private readonly CacheInterface $cachePoolRedis
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_OPTIONAL,
            'The maximum number of `Saved` Content that should be retrieved and processed.',
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
        $messagesOutputSection = $output->section();

        $messagesOutputSection->writeln('<info>-------------Sync API-------------</info>');
        $messagesOutputSection->writeln('<info>Sync Reddit profile `Saved` Contents to local.</info>');

        $savedContents = $this->getSavedContents($input, $messagesOutputSection);

        $messagesOutputSection->writeln([
            sprintf('<comment>%d `Saved` Contents retrieved.</comment>', count($savedContents)),
        ]);

        $result = $this->processedSavedContents($output, $messagesOutputSection, $savedContents);
        if ($result === Command::FAILURE) {
            return $result;
        }

        $messagesOutputSection->writeln([
            '<comment>Sync completed.</comment>',
            sprintf('<comment>%d `Saved` Contents synced.</comment>', count($savedContents))
        ]);

        return Command::SUCCESS;
    }

    /**
     * Retrieve all `Saved` Contents from the Reddit profile.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     *
     * @return int|mixed
     * @throws InvalidArgumentException
     */
    private function getSavedContents(InputInterface $input, OutputInterface $output)
    {
        $maxContents = $input->getOption('limit');
        if (!empty($maxContents) && is_numeric($maxContents)) {
            $maxContents = (int) $maxContents;

            if ($maxContents > 100) {
                $output->writeln('<error>The max number allowed when limiting is 100.</error>');

                return Command::FAILURE;
            }
        }

        $cacheKey = self::CACHE_KEY;
        if (!empty($maxContents)) {
            $cacheKey = $cacheKey . $maxContents;
        }

        $output->writeln('<comment>Retrieving `Saved` Posts from Reddit profile.</comment>');

        return $this->cachePoolRedis->get($cacheKey, function () use ($maxContents, $output) {
            $limit = self::DEFAULT_LIMIT;
            if (!empty($maxContents)) {
                $limit = $maxContents;
            }

            $contents = [];
            $contentsAvailable = true;
            $after = '';
            while ($contentsAvailable) {
                $savedPosts = $this->redditApi->getSavedContents(limit: $limit, after: $after);

                $contents = [...$contents, ...$savedPosts['children']];
                if (!empty($savedPosts['after'])) {
                    $after = $savedPosts['after'];
                } else {
                    $contentsAvailable = false;
                }

                if (!empty($maxContents) && count($contents) >= $maxContents) {
                    $contentsAvailable = false;
                }
            }

            return $contents;
        });
    }

    /**
     * Loop through the provided array of `Saved` Contents and sync them down to
     * local.
     *
     * @param  OutputInterface  $output
     * @param  OutputInterface  $messagesOutputSection
     * @param  array  $savedContents
     *
     * @return int
     * @throws InvalidArgumentException
     */
    private function processedSavedContents(OutputInterface $output, OutputInterface $messagesOutputSection, array $savedContents)
    {
        $messagesOutputSection->writeln('<comment>Syncing `Saved` Contents to local.</comment>');

        $progressBarSection = $output->section();
        $progressBar = new ProgressBar($progressBarSection, count($savedContents));
        $progressBar->start();
        foreach ($savedContents as $savedContent) {
            try {
                $syncedPost = $this->postRepository->findOneBy(['redditId' => $savedContent['data']['id']]);
                if (empty($syncedPost) && empty($savedContent['data']['removed_by_category'])) {
                    $messagesOutputSection->writeln(sprintf('%s: %s', $savedContent['kind'], $savedContent['data']['permalink']), OutputInterface::VERBOSITY_VERBOSE);

                    $post = $this->manager->syncContentFromJsonUrl($savedContent['kind'], $savedContent['data']['permalink']);
                }

                $progressBar->advance();
            } catch (Exception $e) {
                $messagesOutputSection->writeln(sprintf('<error>%s</error>', var_export($savedContent ,true)), OutputInterface::VERBOSITY_VERBOSE);
                $messagesOutputSection->writeln(sprintf('<error>Error: %s', $e->getMessage()));
                $messagesOutputSection->writeln(sprintf('<error>Content: %s: %s</error>', $savedContent['kind'], $savedContent['data']['permalink']));

                // @TODO: This should be removing the actual Content record instead.
                // If the Post was persisted, remove it for re-processing.
                $errorPost = $this->postRepository->findOneBy(['redditId' => $savedContent['data']['id']]);
                if ($errorPost instanceof Post) {
                    $this->postRepository->remove($errorPost, true);
                }

                if ($messagesOutputSection->isVerbose()) {
                    throw $e;
                }

                return Command::FAILURE;
            }
        }

        $progressBar->finish();

        return Command::SUCCESS;
    }
}
