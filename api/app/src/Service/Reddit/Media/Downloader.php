<?php

namespace App\Service\Reddit\Media;

use App\Entity\MediaAsset;
use Exception;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Downloader
{
    public function __construct(private readonly string $publicPath)
    {
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
        $filesystem = new Filesystem();
        $basePath = $this->getBasePathFromMediaAsset($mediaAsset);

        try {
            $filesystem->mkdir(Path::normalize($basePath));
        } catch (IOExceptionInterface $e) {
            throw new Exception(sprintf('An error occurred while creating assets directory at %s: %s', $e->getPath(), $e->getMessage()));
        }

        $assetDownloadPath = $this->getFullPathFromMediaAsset($mediaAsset, $basePath);
        $downloadResult = file_put_contents($assetDownloadPath, file_get_contents($mediaAsset->getSourceUrl()));
        if ($downloadResult === false) {
            throw new Exception(sprintf('Unable to download media asset `%s` from Post `%s`.', $assetDownloadPath, $mediaAsset->getParentPost()->getTitle()));
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
}
