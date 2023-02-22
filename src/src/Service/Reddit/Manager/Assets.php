<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Entity\Asset;
use App\Entity\AssetInterface;
use App\Service\Reddit\Manager\Assets\AssetResponse;
use App\Service\Reddit\Media\Downloader;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Assets
{
    const REDDIT_MEDIA_ASSETS_DIRECTORY_PATH = '/r-media';

    public function __construct(
        private readonly string $publicDirectoryAbsolutePath,
        private readonly Downloader $downloaderService,
        private readonly Filesystem $filesystem,
        private readonly AssetResponse $assetResponseService,
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
     * @return Asset|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function downloadAndProcessAsset(Asset $asset, ?string $filenameFormat = null): ?Asset
    {
        $assetResponse = $this->assetResponseService->getAssetResponse($asset);
        if (($assetResponse instanceof ResponseInterface) === false) {
            return null;
        }

        $assetExtension = $this->getAssetExtensionFromContentTypeHeader($assetResponse);
        // This logic here is to address an edge case in which a gifv URL renders
        // an HTML page instead of the source media. If the header is HTML
        // and the Source URL is an .gifv URL, update the destination to be
        // the media's .mp4 file instead.
        // See DownloaderTest::testVideoHtmlPage() for more information.
        if ($assetExtension === 'html' && str_contains($asset->getSourceUrl(), '.gifv')) {
            $sourceUrl = $asset->getSourceUrl();
            $videoSourceUrl = str_replace('.gifv', '.mp4', $sourceUrl);
            $asset->setSourceUrl($videoSourceUrl);
            $assetExtension = 'mp4';
        }

        $idHash = md5($asset->getSourceUrl());
        $filename = $idHash;
        if (!empty($filenameFormat)) {
            $filename = sprintf($filenameFormat, $idHash);
        }

        $filename .= '.' . $assetExtension;
        $asset->setFilename($filename);
        $asset->setDirOne(substr($idHash, 0, 1));
        $asset->setDirTwo(substr($idHash, 1, 2));

        $assetDirectoryPath = $this->getAssetDirectoryPath($asset, true);
        $this->downloaderService->downloadAsset($asset, $assetDirectoryPath);

        if (!empty($asset->getAudioSourceUrl())) {
            $this->processAudioFile($asset, $assetDirectoryPath);
        }

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
     * Return the appropriate extension for the provided media Content Type
     * value.
     *
     * @param  string  $contentTypeValue
     *
     * @return string
     * @throws Exception
     */
    public function getAssetExtensionFromContentTypeValue(string $contentTypeValue): string
    {
        switch ($contentTypeValue) {
            case 'image/jpg':
            case 'image/jpeg':
                return 'jpg';

            case 'image/png':
                return 'png';

            case 'image/webp':
                return 'webp';

            case 'video/mp4':
            case 'image/gif':
                return 'mp4';

            case 'text/html':
            case 'text/html;charset=UTF-8';
                return 'html';
        }

        throw new Exception(sprintf('Unexpected Content Type in extension extraction: %s', $contentTypeValue));
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

        return $this->getAssetExtensionFromContentTypeValue($headers['content-type'][0]);
    }

    /**
     * Process the audio file associated to the provided Asset by downloading
     * the file from its source URL and merging it with the video file locally.
     *
     * @param  Asset  $asset
     * @param  string  $assetDirectoryPath
     *
     * @return void
     * @throws Exception
     */
    private function processAudioFile(Asset $asset, string $assetDirectoryPath): void
    {
        $videoFile = $assetDirectoryPath . '/' . $asset->getFilename();
        $audioFile = $assetDirectoryPath . '/' . $asset->getAudioFilename();
        $this->downloaderService->downloadSourceToLocalFile($asset->getAudioSourceUrl(), $audioFile);

        $this->mergeVideoAndAudioFiles($assetDirectoryPath, $videoFile, $audioFile);
    }

    /**
     * Merge the provided Reddit Video and Audio files into one video file.
     *
     * Once merged, move the combined file to replace the original Video
     * download path.
     *
     * @param  string  $assetDirectoryPath
     * @param  string  $videoFilePath
     * @param  string  $audioFilePath
     *
     * @return void
     * @throws Exception
     */
    private function mergeVideoAndAudioFiles(string $assetDirectoryPath, string $videoFilePath, string $audioFilePath): void
    {
        $combinedOutputPath = $assetDirectoryPath . '/' . uniqid() . '_combined.mp4';

        $cmd = sprintf('ffmpeg -i %s  -i %s  -c:v copy -c:a aac %s -hide_banner -loglevel error', $videoFilePath, $audioFilePath, $combinedOutputPath);
        $cmdResult = exec($cmd, result_code: $resultCode);

        if ($resultCode != 0 || !empty($cmdResult)) {
            throw new Exception(sprintf('Unexpected command output combining Reddit Video and Audio files. Command: %s. Output: %s. Result Code: %d', $cmd, $cmdResult, $resultCode));
        }

        // Rename the combined output to the original video filename.
        // Also, remove the Audio file as it's no longer needed.
        $this->filesystem->rename($combinedOutputPath, $videoFilePath, true);
        $this->filesystem->remove($audioFilePath);
    }
}
