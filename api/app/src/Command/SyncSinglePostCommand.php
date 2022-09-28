<?php

namespace App\Command;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Service\Reddit\Hydrator;
use App\Service\Reddit\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:sync-single-post',
    description: 'Sync a single Post to local by Reddit ID.',
    aliases: ['app:sync-single'],
)]
class SyncSinglePostCommand extends Command
{
    public function __construct(private readonly Manager $manager, private readonly PostRepository $postRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $redditId = 'ip7pedq';
        $kind = Hydrator::TYPE_COMMENT;
        $postLink = 'https://www.reddit.com/r/gaming/comments/xj8f7g/comment/ip7pedq/';

        $post = $this->manager->syncPostFromJsonUrl($kind, $postLink);

        $post = $this->postRepository->findOneBy(['redditId' => $redditId]);

        return Command::SUCCESS;
    }
}