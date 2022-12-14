<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\Kind;
use App\Entity\Post;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Service\Reddit\Api;
use Psr\Cache\InvalidArgumentException;

class SavedContents
{
    const DEFAULT_LIMIT = 100;

    const BATCH_SIZE = 100;

    public function __construct(
        private readonly Api $redditApi,
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository
    ) {
    }

    /**
     * Retrieve the `Saved` Contents data from the Reddit profile but only
     * return the subset of Contents data which have *not* been persisted
     * locally yet.
     *
     * @param  int  $limit
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getNonLocalSavedContentsData(int $limit = self::DEFAULT_LIMIT): array
    {
        $nonLocalSavedContentsData = [];
        $savedContentsData = $this->getSavedContentsData();

        foreach ($savedContentsData as $savedContentData) {
            $localContent = $this->getLocalContentFromSavedContentData($savedContentData);
            if ($localContent instanceof Content) {
                continue;
            }

            $nonLocalSavedContentsData[] = $savedContentData;
            if (count($nonLocalSavedContentsData) >= $limit) {
                break;
            }
        }

        return $nonLocalSavedContentsData;
    }

    /**
     * Retrieve all `Saved` Contents data from the Reddit profile.
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getSavedContentsData(): array
    {
        $contents = [];
        $contentsAvailable = true;
        $after = '';
        while ($contentsAvailable) {
            $savedContents = $this->redditApi->getSavedContents(limit: self::BATCH_SIZE, after: $after);

            $contents = [...$contents, ...$savedContents['children']];
            if (!empty($savedContents['after'])) {
                $after = $savedContents['after'];
            } else {
                $contentsAvailable = false;
            }

            if (!empty($maxContents) && count($contents) >= $maxContents) {
                $contentsAvailable = false;
            }
        }

        return $contents;
    }

    /**
     * Based on the provided `Saved` Content data, find and return the locally
     * persisted Content Entity, if any.
     *
     * @param  array  $savedContentData
     *
     * @return Content|null
     */
    public function getLocalContentFromSavedContentData(array $savedContentData): ?Content
    {
        $localContent = null;
        $redditId = $savedContentData['data']['id'];
        if ($savedContentData['kind'] === Kind::KIND_COMMENT) {
            $comment = $this->commentRepository->findOneBy(['redditId' => $redditId]);
            if ($comment instanceof Comment) {
                $localContent = $comment->getContent();
            }
        } else {
            $post = $this->postRepository->findOneBy(['redditId' => $redditId]);
            if ($post instanceof Post) {
                $localContent = $post->getContent();
            }
        }

        return $localContent;
    }
}
