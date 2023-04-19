<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\Typesense\Collection\Contents;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;

#[AsCommand(
    name: 'search:clear-index',
    description: 'Clear the Search index by re-creating the Contents schema.',
)]
class ClearSearchIndexCommand extends Command
{
    public function __construct(private readonly ParameterBagInterface $parameterBag)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiKey = $this->parameterBag->get('app.typesense.api_key');

        $client = new Client(
            [
                'api_key' => $apiKey,
                'nodes' => [
                    [
                        'host' => 'localhost',
                        'port' => '8108',
                        'protocol' => 'http',
                    ],
                ],
                'client' => new HttplugClient(),
            ]
        );

        $client->collections['contents']->delete();
        $client->collections->create(Contents::SCHEMA);

        return Command::SUCCESS;
    }
}
