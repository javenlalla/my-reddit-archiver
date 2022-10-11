<?php

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
    name: 'app:process-saved-posts',
    description: 'Loop through your Saved Posts and persist them locally.',
    aliases: ['app:process-saved'],
    hidden: false
)]
class ProcessSavedPostsCommand extends Command
{
    const DEFAULT_LIMIT = 100;

    const CACHE_KEY = 'saved-posts-command';

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
            'The maximum number of `Saved` Posts that should be retrieved and processed.',
        );
    }

    /**
     * Entry function to begin syncing the Reddit profile's `Saved` Posts to
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
        $messagesOutputSection->writeln('<info>Sync Reddit profile `Saved` Posts to local.</info>');

        $savedPosts = $this->getSavedPosts($input, $messagesOutputSection);

        $messagesOutputSection->writeln([
            sprintf('<comment>%d Posts retrieved.</comment>', count($savedPosts)),
        ]);

        $result = $this->processedSavedPosts($output, $messagesOutputSection, $savedPosts);
        if ($result === Command::FAILURE) {
            return $result;
        }

        $messagesOutputSection->writeln([
            '<comment>Sync completed.</comment>',
            sprintf('<comment>%d `Saved` Posts synced.</comment>', count($savedPosts))
        ]);

        return Command::SUCCESS;
    }

    /**
     * Retrieve all `Saved` Posts from the Reddit profile.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     *
     * @return int|mixed
     * @throws InvalidArgumentException
     */
    private function getSavedPosts(InputInterface $input, OutputInterface $output)
    {
        $maxPosts = $input->getOption('limit');
        if (!empty($maxPosts) && is_numeric($maxPosts)) {
            $maxPosts = (int) $maxPosts;

            if ($maxPosts > 100) {
                $output->writeln('<error>The max number allowed when limiting is 100.</error>');

                return Command::FAILURE;
            }
        }

        $cacheKey = self::CACHE_KEY;
        if (!empty($maxPosts)) {
            $cacheKey = $cacheKey . $maxPosts;
        }

        $output->writeln('<comment>Retrieve `Saved` Posts from Reddit profile.</comment>');

        return $this->cachePoolRedis->get($cacheKey, function () use ($maxPosts, $output) {
            $limit = self::DEFAULT_LIMIT;
            if (!empty($maxPosts)) {
                $limit = $maxPosts;
            }

            $posts = [];
            $postsAvailable = true;
            $after = '';
            while ($postsAvailable) {
                $savedPosts = $this->redditApi->getSavedPosts(limit: $limit, after: $after);

                $posts = [...$posts, ...$savedPosts['children']];
                if (!empty($savedPosts['after'])) {
                    $after = $savedPosts['after'];
                } else {
                    $postsAvailable = false;
                }

                if (!empty($maxPosts) && count($posts) >= $maxPosts) {
                    $postsAvailable = false;
                }

                $output->writeln(sprintf('<comment>%d `Saved` Posts retrieved.</comment>', count($posts)));
            }

            return $posts;
        });
    }

    /**
     * Loop through the provided array of `Saved` Posts and sync them down to
     * local.
     *
     * @param  OutputInterface  $output
     * @param  OutputInterface  $messagesOutputSection
     * @param  array  $savedPosts
     *
     * @return int
     * @throws InvalidArgumentException
     */
    private function processedSavedPosts(OutputInterface $output, OutputInterface $messagesOutputSection, array $savedPosts)
    {
        $messagesOutputSection->writeln('<comment>Sync `Saved` Posts to local.</comment>');

        $progressBarSection = $output->section();
        $progressBar = new ProgressBar($progressBarSection, count($savedPosts));
        $progressBar->start();
        foreach ($savedPosts as $savedPost) {
            try {
                $syncedPost = $this->postRepository->findOneBy(['redditId' => $savedPost['data']['id']]);
                if (empty($syncedPost) && empty($savedPost['data']['removed_by_category'])) {
                    $messagesOutputSection->writeln(sprintf('%s: %s', $savedPost['kind'], $savedPost['data']['permalink']), OutputInterface::VERBOSITY_VERBOSE);

                    $post = $this->manager->syncPostFromJsonUrl($savedPost['kind'], $savedPost['data']['permalink']);
                }

                $progressBar->advance();
            } catch (Exception $e) {
                $messagesOutputSection->writeln(sprintf('<error>%s</error>', var_export($savedPost ,true)), OutputInterface::VERBOSITY_VERBOSE);
                $messagesOutputSection->writeln(sprintf('<error>Error: %s', $e->getMessage()));
                $messagesOutputSection->writeln(sprintf('<error>Post: %s: %s</error>', $savedPost['kind'], $savedPost['data']['permalink']));

                // If the Post was persisted, remove it for re-processing.
                $errorPost = $this->postRepository->findOneBy(['redditId' => $savedPost['data']['id']]);
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
