<?php

namespace App\Command;

use App\Entity\Post;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
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
    public function __construct(private readonly Manager $manager, private readonly PostRepository $postRepository, private readonly CommentRepository $commentRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // @TODO: Convert these to arguments provided to the script.
        $redditId = 'cs8urd';
        $kind = Type::TYPE_LINK;
        $postLink = '/r/SquaredCircle/comments/cs8urd/matt_riddle_got_hit_by_a_truck/';

        $purge = true;
        if ($purge) {
            $post = $this->postRepository->findOneBy(['redditId' => $redditId]);
            if ($post instanceof Post) {
                foreach ($post->getComments() as $comment) {
                    if ($comment->getParentComment() === null) {
                        $this->commentRepository->remove($comment, true);
                    }
                }

                $this->postRepository->remove($post, true);
            }
        }

        $post = $this->manager->syncPostFromJsonUrl($kind,  $postLink);

        $post = $this->postRepository->findOneBy(['redditId' => $redditId]);

        return Command::SUCCESS;
    }
}
