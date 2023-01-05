<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Entity\AssetInterface;

class Assets
{
    const REDDIT_MEDIA_ASSETS_DIRECTORY_PATH = '/r-media';

    public function __construct(
        private readonly string $publicDirectoryAbsolutePath,
    ) {
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
        $basePath = $this->getAssetBasePath($asset, $absolutePath);

        return $basePath . '/' . $asset->getFilename();
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
}
