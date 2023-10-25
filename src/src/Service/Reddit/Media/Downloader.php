<?php
declare(strict_types=1);

namespace App\Service\Reddit\Media;

use App\Entity\Asset;
use Exception;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Downloader
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Main function to download the provided Asset Entity to local.
     *
     * @param  Asset  $asset
     * @param  string  $assetDirectoryPath
     *
     * @return void
     * @throws Exception
     */
    public function downloadAsset(Asset $asset, string $assetDirectoryPath): void
    {
        try {
            $this->filesystem->mkdir(Path::normalize($assetDirectoryPath));
        } catch (IOExceptionInterface $e) {
            throw new Exception(sprintf('An error occurred while creating assets directory at %s: %s', $e->getPath(), $e->getMessage()));
        }

        $assetPath = $assetDirectoryPath . '/' . $asset->getFilename();
        $this->downloadSourceToLocalFile($asset->getSourceUrl(), $assetPath);
    }

    /**
     * Direct function to download an asset or file from the provided URL to the
     * targeted local download path.
     *
     * Note: the asset is only downloaded if it does not already exist locally.
     *
     * @param  string  $sourceUrl
     * @param  string  $targetFilepath
     *
     * @return void
     * @throws TransportExceptionInterface
     */
    public function downloadSourceToLocalFile(string $sourceUrl, string $targetFilepath): void
    {
        if ($this->filesystem->exists($targetFilepath) === false) {
            $response = $this->httpClient->request('GET', $sourceUrl);
            if (200 !== $response->getStatusCode()) {
                throw new Exception(sprintf('Unable to reach URL for download: %s', $sourceUrl));
            }

            $fileHandler = fopen($targetFilepath, 'w');
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($fileHandler, $chunk->getContent());
            }
        }
    }
}
