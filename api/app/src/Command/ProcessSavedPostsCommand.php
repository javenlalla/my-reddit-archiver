<?php

namespace App\Command;

use App\Service\Reddit\Api;
use App\Service\Reddit\Manager;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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

    public function __construct(private readonly Manager $manager, private readonly Api $redditApi, private readonly CacheInterface $cachePoolRedis)
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_OPTIONAL,
            'The maximum number of Saved Posts that should be retrieved and processed.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $savedPosts = $this->getSavedPosts($input, $output);

        $output->writeln([
            sprintf('<comment>%d Posts retrieved.</comment>', count($savedPosts)),
        ]);

        $savedCount = 0;
        foreach ($savedPosts as $savedPost) {
            try {
                $post = $this->manager->syncPost($savedPost);
            } catch (Exception $e) {
                file_put_contents('error.json', json_encode($savedPost));
                $output->writeln(sprintf('<error>Error thrown for post: %s</error>', var_export($savedPost ,true)));
                throw $e;
            }

            $savedCount++;
            if ($savedCount % 10 === 0) {
                $output->writeln(sprintf('<comment>%d Posts saved.</comment>', $savedCount));
            }
        }

        $output->writeln([
            '<info>Processing completed.</info>',
            sprintf('<info>%d Posts saved.</info>', $savedCount)
        ]);

        return Command::SUCCESS;
    }

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

        return $this->cachePoolRedis->get($cacheKey, function () use ($maxPosts) {
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
            }

            return $posts;
        });
    }
}
