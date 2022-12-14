<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\ApiUser;
use App\Repository\ApiUserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:persist-reddit-account',
    description: 'Save the configured Reddit account to the database, if not persisted already.',
)]
class PersistRedditAccountCommand extends Command
{
    public function __construct(private readonly ApiUserRepository $apiUserRepository, private readonly ParameterBagInterface $parameterBag)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $this->parameterBag->get('app.reddit.username');

        $existingUserEntity = $this->apiUserRepository->findOneBy(['username' => $username]);
        if ($existingUserEntity instanceof ApiUser) {
            return Command::SUCCESS;
        }

        $apiUser = new ApiUser();
        $apiUser->setUsername($username);

        $this->apiUserRepository->add($apiUser, true);

        return Command::SUCCESS;
    }
}
