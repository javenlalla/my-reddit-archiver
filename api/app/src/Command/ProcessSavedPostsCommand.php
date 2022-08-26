<?php

namespace App\Command;

use App\Service\Reddit\Api;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsCommand(
    name: 'app:process-saved-posts',
    description: 'Loop through your Saved Posts and persist them locally.',
    aliases: ['app:process-saved'],
    hidden: false
)]
class ProcessSavedPostsCommand extends Command
{
    public function __construct(private readonly Api $redditApi, private readonly CacheInterface $cachePoolRedis)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $posts = $this->cachePoolRedis->get('saved-posts', function (ItemInterface $item) {
            $posts = [];
            $postsAvailable = true;
            $after = '';
            $count = 0;
            while ($postsAvailable) {
                $savedPosts = $this->redditApi->getSavedPosts(after: $after);

                $posts = [...$posts, ...$savedPosts['children']];
                if (!empty($savedPosts['after'])) {
                    $after = $savedPosts['after'];
                } else {
                    $postsAvailable = false;
                }

                $count++;
            }

            return $posts;
        });
        $output->writeln([
            sprintf('<info>%d Posts retrieved.</info>', count($posts)),
            '',
        ]);

        return Command::SUCCESS;
    }
}