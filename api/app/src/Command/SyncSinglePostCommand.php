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
        $redditId = 'vepbt0';
        $kind = Hydrator::TYPE_LINK;
        $permalinkUrl = 'https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/';
        $payload = [
            'kind' => $kind,
            'data' => [
                'link_permalink' => $permalinkUrl,
            ]
        ];

        $post = $this->manager->syncPostFromJsonUrl($payload);

        $post = $this->postRepository->findOneBy(['redditId' => $redditId]);
        $comments = $post->getComments();

        return Command::SUCCESS;
    }
}