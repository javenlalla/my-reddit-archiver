<?php

namespace App\Service\Reddit\Media;

use App\Entity\MediaAsset;
use Exception;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Downloader
{
    public function __construct(
        private readonly string $publicPath,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * Execute the download of the provided Media Asset and save the target file
     * locally.
     *
     * @param  MediaAsset  $mediaAsset
     *
     * @return void
     * @throws Exception
     */
    public function executeDownload(MediaAsset $mediaAsset): void
    {
        $basePath = $this->getBasePathFromMediaAsset($mediaAsset);

        try {
            $this->filesystem->mkdir(Path::normalize($basePath));
        } catch (IOExceptionInterface $e) {
            throw new Exception(sprintf('An error occurred while creating assets directory at %s: %s', $e->getPath(), $e->getMessage()));
        }

        $assetDownloadPath = $this->getFullPathFromMediaAsset($mediaAsset, $basePath);
        $downloadResult = file_put_contents($assetDownloadPath, file_get_contents($mediaAsset->getSourceUrl()));
        if ($downloadResult === false) {
            throw new Exception(sprintf('Unable to download media asset `%s` from Post `%s`.', $assetDownloadPath, $mediaAsset->getParentPost()->getTitle()));
        }

        if (!empty($mediaAsset->getAudioSourceUrl())) {
            $this->downloadAndMergeVideoAudio($mediaAsset, $assetDownloadPath, $basePath);
        }
    }

    /**
     * Return the full path to the `assets` folder in which Post media files
     * are stored locally.
     *
     * @return string
     */
    private function getAssetsPath(): string
    {
        return $this->publicPath . '/assets';
    }

    /**
     * Formulate and return the base path to the parent directory holding the
     * provided Media Asset.
     *
     * @param  MediaAsset  $mediaAsset
     *
     * @return string
     */
    private function getBasePathFromMediaAsset(MediaAsset $mediaAsset): string
    {
        return $this->getAssetsPath() . '/' . $mediaAsset->getDirOne() . '/' . $mediaAsset->getDirTwo();
    }

    /**
     * Formulate and return the full path to the provided Media Asset file.
     *
     * @param  MediaAsset  $mediaAsset
     * @param  string  $basePath    Optional path to provide to avoid calling
     *                              the getBasePathFromMediaAsset() function unnecessarily.
     *
     * @return string
     */
    private function getFullPathFromMediaAsset(MediaAsset $mediaAsset, string $basePath = ''): string
    {
        if (empty($basePath)) {
            return $this->getBasePathFromMediaAsset($mediaAsset) . '/' . $mediaAsset->getFilename();
        }

        return $basePath . '/' . $mediaAsset->getFilename();
    }

    /**
     * Download the Audio file associated to the provided Media Asset and
     * merge it into the Video file.
     *
     * @param  MediaAsset  $mediaAsset
     * @param  string  $videoDownloadPath
     * @param  string  $basePath
     *
     * @return void
     * @throws Exception
     */
    private function downloadAndMergeVideoAudio(MediaAsset $mediaAsset, string $videoDownloadPath, string $basePath): void
    {
        $audioDownloadPath = $basePath . '/' . $mediaAsset->getAudioFilename();
        $downloadResult = file_put_contents($audioDownloadPath, file_get_contents($mediaAsset->getAudioSourceUrl()));
        if ($downloadResult === false) {
            throw new Exception(sprintf('Unable to download media asset `%s` from Post `%s`.', $audioDownloadPath, $mediaAsset->getParentPost()->getTitle()));
        }

        $this->mergeVideoAndAudioFiles($basePath, $videoDownloadPath, $audioDownloadPath);
        // Audio file is no longer needed locally once merged into the Video file.
        $this->filesystem->remove($audioDownloadPath);
    }

    /**
     * Merge the provided Reddit Video and Audio files into one video file.
     *
     * Once merged, move the combined file to replace the original Video
     * download path.
     *
     * @param  string  $basePath
     * @param  string  $videoDownloadPath
     * @param  string  $audioDownloadPath
     *
     * @return void
     * @throws Exception
     */
    private function mergeVideoAndAudioFiles(string $basePath, string $videoDownloadPath, string $audioDownloadPath): void
    {
        $combinedOutputPath = $basePath . '/' . uniqid() . '_combined.mp4';

        $cmd = sprintf('ffmpeg -i %s  -i %s  -c:v copy -c:a aac %s -hide_banner -loglevel error', $videoDownloadPath, $audioDownloadPath, $combinedOutputPath);
        $cmdResult = exec($cmd, result_code: $resultCode);

        if ($resultCode !== 0 || !empty($cmdResult)) {
            throw new Exception(sprintf('Unexpected command output combining Reddit Video and Audio files. Command: %s. Output: %s. Result Code: %d', $cmd, $cmdResult, $resultCode));
        }

        $this->filesystem->rename($combinedOutputPath, $videoDownloadPath, true);
    }
}
