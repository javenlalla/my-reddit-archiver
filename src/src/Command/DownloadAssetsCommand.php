<?php
declare(strict_types=1);

namespace App\Command;

use App\Repository\AssetRepository;
use App\Service\Reddit\Manager\Assets;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'reddit:download-assets',
    description: 'Loop through non-downloaded Assets and initiate downloading to store the Asset locally.'
)]
class DownloadAssetsCommand extends Command
{
    const DEFAULT_LIMIT = 100;

    public function __construct(private readonly AssetRepository $assetRepository, private readonly Assets $assetsManager, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'limit',
                mode: InputArgument::OPTIONAL,
                description: 'Sample argument provided to command.',
                default: self::DEFAULT_LIMIT
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $limit = (int) $input->getOption('limit');
        if (empty($limit)) {
            $limit = self::DEFAULT_LIMIT;
        }

        $assets = $this->assetRepository->findByDownloadStatus(false, $limit);
        $output->writeln(sprintf('Retrieved %d assets to download.', count($assets)));

        $downloadCount = 0;
        foreach ($assets as $asset) {
            $downloadedAsset = $this->assetsManager->downloadAsset($asset);

            if ($downloadedAsset->isDownloaded() === true) {
                $this->entityManager->persist($downloadedAsset);

                $downloadCount++;
                if (($downloadCount % 25) === 0) {
                    $this->entityManager->flush();
                }
            }
        }
        $this->entityManager->flush();

        $output->writeln(sprintf('%s Assets have been successfully downloaded.', $downloadCount));

        return Command::SUCCESS;
    }
}
