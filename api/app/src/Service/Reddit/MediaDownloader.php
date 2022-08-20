<?php

namespace App\Service\Reddit;

use App\Entity\Post;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class MediaDownloader
{
    public function __construct(private readonly string $publicPath)
    {
    }

    public function downloadMediaFromPost(Post $post)
    {
        $path = $this->getFullDownloadFilePath($post);

        $url = 'https://i.imgur.com/ThRMZx5.jpg';
        file_put_contents($path, file_get_contents($url));

        // @TODO: Determine if Gallery or single image.
    }

    public function getFullDownloadFilePath(Post $post): string
    {
        $idHash = md5($post->getRedditId());
        $subDirOne = substr($idHash, 0, 1);
        $subDirTwo = substr($idHash, 2, 2);

        $basePath = $this->getAssetsPath() . '/' . $subDirOne . '/' . $subDirTwo;

        $filesystem = new Filesystem();

        try {
            $filesystem->mkdir(Path::normalize($basePath));
        } catch (IOExceptionInterface $e) {
            throw new \Exception(sprintf('An error occurred while creating assets directory at %s: %s', $e->getPath(), $e->getMessage()));
        }

        // @TODO: Add extension detection logic.
        $fullPath = $basePath . '/' . $idHash . '.jpg';

        return $fullPath;
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
}