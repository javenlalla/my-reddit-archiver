<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\ContentPendingSync;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\ProfileContentGroup;
use App\Helper\FullRedditIdHelper;
use App\Repository\CommentRepository;
use App\Repository\ContentPendingSyncRepository;
use App\Repository\ContentRepository;
use App\Repository\PostRepository;
use App\Repository\ProfileContentGroupRepository;
use App\Service\Reddit\Api;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;

class SavedContents
{
    const DEFAULT_LIMIT = 100;

    const BATCH_SIZE = 100;

    const PERSISTENCE_BATCH_SIZE = 25;

    public function __construct(
        private readonly Api $redditApi,
        private readonly EntityManagerInterface $entityManager,
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ContentRepository $contentRepository,
        private readonly ContentPendingSyncRepository $contentPendingSyncRepository,
        private readonly ProfileContentGroupRepository $profileContentGroupRepository,
        private readonly FullRedditIdHelper $fullRedditIdHelper,
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
     * Get any Content Entities under the `Saved` group that are still pending
     * a sync.
     *
     * @param  int  $limit
     * @param  bool  $fetchPendingEntities
     *
     * @return ContentPendingSync[]
     * @throws InvalidArgumentException
     */
    public function getContentsPendingSync(int $limit = self::DEFAULT_LIMIT, bool $fetchPendingEntities = false): array
    {
        if ($fetchPendingEntities) {
            $this->getSavedContentsData();
        }

        $savedGroup = $this->profileContentGroupRepository->getGroupByName(ProfileContentGroup::PROFILE_GROUP_SAVED);

        if ($limit < 1) {
            $limit = null;
        }

        return $this->contentPendingSyncRepository->findBy(['profileContentGroup' => $savedGroup], null, $limit);
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

        $persistedCount = 0;
        foreach ($contents as $content) {
            // Saved a full_reddit_id on the `content` table. Use that as a look-up to see if this `pending_sync` record should be saved
            $fullRedditId = $this->fullRedditIdHelper->formatFullRedditId($content['kind'], $content['data']['id']);

            $syncedContent = $this->contentRepository->findOneBy(['fullRedditId' => $fullRedditId]);

            if (empty($syncedContent)) {
                $pendingSync = new ContentPendingSync();
                $pendingSync->setJsonData(json_encode($content));
                $pendingSync->setFullRedditId($fullRedditId);

                $savedGroup = $this->profileContentGroupRepository->getGroupByName(ProfileContentGroup::PROFILE_GROUP_SAVED);
                $pendingSync->setProfileContentGroup($savedGroup);

                $this->entityManager->persist($pendingSync);

                $persistedCount++;
                if (($persistedCount % self::PERSISTENCE_BATCH_SIZE) === 0) {
                    $this->entityManager->flush();
                    $persistedCount = 0;
                }
            }
        }

        // Flush any lingering persisted Entities.
        if ($persistedCount > 0) {
            $this->entityManager->flush();
        }

        // Return query finding all pending syncs (by category?) limited by provided limit param.

        return $contents;
    }

    /**
     * Refresh the local list of Contents pending sync by comparing to the
     * latest list of Content results on Reddit's side under all Profile
     * Content Groups.
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function refreshAllPendingEntities(): void
    {
        $profileGroups = [
            ProfileContentGroup::PROFILE_GROUP_SAVED,
        ];

        foreach ($profileGroups as $profileGroup) {
            $this->refreshPendingEntitiesByProfileGroup($profileGroup);
        }
    }

    /**
     * Refresh the local list of Contents pending sync by comparing to the
     * latest list of Content results on Reddit's side under the specified
     * Profile Content Group.
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function refreshPendingEntitiesByProfileGroup(string $profileGroup): void
    {
        $moreContentsAvailable = true;
        $after = '';
        while ($moreContentsAvailable) {
            $savedContents = $this->redditApi->getSavedContents(limit: self::BATCH_SIZE, after: $after);

            if (!empty($savedContents['children'])) {
                $this->addContentsToPendingSync($savedContents['children']);
            }

            if (!empty($savedContents['after'])) {
                $after = $savedContents['after'];
            } else {
                $moreContentsAvailable = false;
            }
        }
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

    /**
     * Persist the provided array of raw Contents data to the database.
     *
     * @param  array  $contentsData
     *
     * @return void
     */
    private function addContentsToPendingSync(array $contentsData)
    {
        $persistedCount = 0;
        foreach ($contentsData as $contentData) {
            $fullRedditId = $this->fullRedditIdHelper->formatFullRedditId($contentData['kind'], $contentData['data']['id']);

            $syncedContent = $this->contentRepository->findOneBy(['fullRedditId' => $fullRedditId]);
            $existingPendingContent = $this->contentPendingSyncRepository->findOneBy(['fullRedditId' => $fullRedditId]);

            if (empty($syncedContent) && empty($existingPendingContent)) {
                $pendingSync = new ContentPendingSync();
                $pendingSync->setJsonData(json_encode($contentData));
                $pendingSync->setFullRedditId($fullRedditId);

                $savedGroup = $this->profileContentGroupRepository->getGroupByName(ProfileContentGroup::PROFILE_GROUP_SAVED);
                $pendingSync->setProfileContentGroup($savedGroup);

                $this->entityManager->persist($pendingSync);

                $persistedCount++;
                if (($persistedCount % self::PERSISTENCE_BATCH_SIZE) === 0) {
                    $this->entityManager->flush();
                    $persistedCount = 0;
                }
            }
        }

        // Flush any lingering persisted Entities.
        if ($persistedCount > 0) {
            $this->entityManager->flush();
        }

        $this->entityManager->clear();
    }
}
