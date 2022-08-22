<?php

namespace App\Service\Reddit\Media;

use App\Entity\ContentType;
use App\Entity\MediaAsset;
use App\Entity\Post;
use App\Repository\MediaAssetRepository;
use App\Repository\PostRepository;
use Exception;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Downloader
{
    public function __construct(private readonly PostRepository $postRepository, private readonly MediaAssetRepository $mediaAssetRepository, private readonly string $publicPath)
    {
    }

    /**
     * Main function to download any Media Assets associated to the provided
     * Post and persist them to the database.
     *
     * @param  Post  $post
     *
     * @return Post
     * @throws Exception
     */
    public function downloadMediaFromPost(Post $post): Post
    {
        $contentType = $post->getContentType()->getName();

        if ($contentType === ContentType::CONTENT_TYPE_IMAGE) {
            $mediaAsset = $this->initializeMediaAssetFromPost($post);
            $this->executeDownload($mediaAsset);

            $post->addMediaAsset($mediaAsset);
            $this->postRepository->add($post, true);
        } /*else if ($contentType === ContentType::CONTENT_TYPE_IMAGE_GALLERY) {

        }*/

        return $post;
    }

    /**
     * Initialize a new Media Asset entity with expected pathing from the
     * provided Post.
     *
     * @param  Post  $post
     *
     * @return MediaAsset
     */
    private function initializeMediaAssetFromPost(Post $post): MediaAsset
    {
        $mediaAsset = new MediaAsset();
        $mediaAsset->setParentPost($post);

        $idHash = md5($post->getRedditId());
        // @TODO: Add extension detection logic.
        $mediaAsset->setFilename($idHash . '.jpg');

        $mediaAsset->setDirOne(substr($idHash, 0, 1));
        $mediaAsset->setDirTwo(substr($idHash, 2, 2));

        $this->mediaAssetRepository->add($mediaAsset, true);

        return $mediaAsset;
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
     * Execute the download of the provided Media Asset and save the target file
     * locally.
     *
     * @param  MediaAsset  $mediaAsset
     *
     * @return void
     * @throws Exception
     */
    private function executeDownload(MediaAsset $mediaAsset)
    {
        $filesystem = new Filesystem();

        $basePath = $this->getBasePathFromMediaAsset($mediaAsset);

        try {
            $filesystem->mkdir(Path::normalize($basePath));
        } catch (IOExceptionInterface $e) {
            throw new Exception(sprintf('An error occurred while creating assets directory at %s: %s', $e->getPath(), $e->getMessage()));
        }

        $assetDownloadPath = $this->getFullPathFromMediaAsset($mediaAsset, $basePath);

        $downloadResult = file_put_contents($assetDownloadPath, file_get_contents($mediaAsset->getParentPost()->getUrl()));

        if ($downloadResult === false) {
            throw new Exception(sprintf('Unable to download media asset `%s` from Post `%s`.', $assetDownloadPath, $mediaAsset->getParentPost()->getTitle()));
        }
    }
}
