<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Entity\Asset;
use App\Entity\AssetInterface;
use App\Service\Reddit\Media\Downloader;
use Exception;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Assets
{
    const REDDIT_MEDIA_ASSETS_DIRECTORY_PATH = '/r-media';

    public function __construct(
        private readonly string $publicDirectoryAbsolutePath,
        private readonly HttpClientInterface $httpClient,
        private readonly Downloader $downloaderService,
    ) {
    }

    /**
     * Download the asset file associated to the provided Asset Entity and update
     * the Entity properties with the local file's metadata including its
     * filename and directory level names.
     *
     * @param  Asset  $asset
     * @param  string|null  $filenameFormat
     *
     * @return Asset
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function downloadAndProcessAsset(Asset $asset, ?string $filenameFormat = null): Asset
    {
        $response = $this->httpClient->request('GET', $asset->getSourceUrl());
        if (200 !== $response->getStatusCode()) {
            throw new Exception(sprintf('Unable to retrieve Asset from URL: %s. Response: %s', $asset->getSourceUrl(), $response->getContent()));
        }

        $idHash = md5($asset->getSourceUrl());
        $filename = $idHash;
        if (!empty($filenameFormat)) {
            $filename = sprintf($filenameFormat, $idHash);
        }

        $assetExtension = $this->getAssetExtensionFromContentTypeHeader($response);
        $filename .= '.' . $assetExtension;

        $asset->setFilename($filename);
        $asset->setDirOne(substr($idHash, 0, 1));
        $asset->setDirTwo(substr($idHash, 1, 2));

        $this->downloaderService->downloadAsset($asset, $this->getAssetDirectoryPath($asset, true));

        return $asset;
    }

    /**
     * Get the public local path to the provided Asset.
     *
     * @param  AssetInterface  $asset
     * @param  bool  $absolutePath
     *
     * @return string
     */
    public function getAssetPath(AssetInterface $asset, bool $absolutePath = false): string
    {
        return $this->getAssetDirectoryPath($asset, $absolutePath) . '/' . $asset->getFilename();
    }

    /**
     * Get the public local path to the directory intended to house the
     * provided Asset.
     *
     * @param  AssetInterface  $asset
     * @param  bool  $absolutePath
     *
     * @return string
     */
    public function getAssetDirectoryPath(AssetInterface $asset, bool $absolutePath = false): string
    {
        return $this->getAssetBasePath($asset, $absolutePath);
    }

    /**
     * Formulate and return the base path to the parent directory holding the
     * provided Asset.
     *
     * @param  AssetInterface  $asset
     * @param  bool  $absolutePath
     *
     * @return string
     */
    private function getAssetBasePath(AssetInterface $asset, bool $absolutePath = false): string
    {
        return $this->getPublicAssetsDirectoryPath($absolutePath) . '/' . $asset->getDirOne() . '/' . $asset->getDirTwo();
    }

    /**
     * Return the full path to the public folder in which Asset files are
     * stored locally.
     *
     * @param  bool  $absolutePath
     *
     * @return string
     */
    private function getPublicAssetsDirectoryPath(bool $absolutePath = false): string
    {
        $path = self::REDDIT_MEDIA_ASSETS_DIRECTORY_PATH;

        if ($absolutePath === true) {
            $path = $this->publicDirectoryAbsolutePath . $path;
        }

        return $path;
    }

    /**
     * Based on the Content-Type header attached to the provided Response,
     * return the appropriate extension for the Asset file type.
     *
     * @param  ResponseInterface  $response
     *
     * @return string
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function getAssetExtensionFromContentTypeHeader(ResponseInterface $response): string
    {
        $headers = $response->getHeaders();
        if (empty($headers['content-type'][0])) {
            return 'jpg';
        }

        switch ($headers['content-type'][0]) {
            case 'image/jpg':
                return 'jpg';

            case 'image/jpeg':
                return 'jpeg';

            case 'image/png':
                return 'png';

            case 'image/webp':
                return 'webp';

            case 'image/gif':
                return 'mp4';
        }

        return 'jpg';
    }
}
