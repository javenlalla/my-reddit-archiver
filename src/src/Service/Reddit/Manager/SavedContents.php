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
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Items;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;

class SavedContents
{
    const DEFAULT_LIMIT = 100;

    const BATCH_SIZE = 100;

    const PERSISTENCE_BATCH_SIZE = 25;

    public function __construct(
        private readonly Api $redditApi,
        private readonly Items $itemsService,
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
     * Get any Content Entities under the targeted group that are still pending
     * a sync.
     *
     * @param  string|null  $profileGroupName
     * @param  int  $limit
     * @param  bool  $fetchPendingEntities
     *
     * @return ContentPendingSync[]
     * @throws InvalidArgumentException
     */
    public function getContentsPendingSync(?string $profileGroupName = null, int $limit = self::DEFAULT_LIMIT, bool $fetchPendingEntities = false): array
    {
        if ($fetchPendingEntities) {
            $this->getSavedContentsData();
        }

        if ($limit < 1) {
            $limit = null;
        }

        $profileContentGroupClause = [];
        if (!empty($profileGroupName)) {
            $group = $this->profileContentGroupRepository->getGroupByName($profileGroupName);
            $profileContentGroupClause = ['profileContentGroup' => $group];
        }

        return $this->contentPendingSyncRepository->findBy($profileContentGroupClause, null, $limit);
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
     * @param  Context  $context
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function refreshAllPendingEntities(Context $context): void
    {
        $profileGroups = [
            ProfileContentGroup::PROFILE_GROUP_SAVED,
            ProfileContentGroup::PROFILE_GROUP_COMMENTS,
            ProfileContentGroup::PROFILE_GROUP_UPVOTED,
            ProfileContentGroup::PROFILE_GROUP_DOWNVOTED,
            ProfileContentGroup::PROFILE_GROUP_SUBMITTED,
            ProfileContentGroup::PROFILE_GROUP_GILDED,
        ];

        foreach ($profileGroups as $profileGroup) {
            $this->refreshPendingEntitiesByProfileGroup($context, $profileGroup);
        }
    }

    /**
     * Refresh the local list of Contents pending sync by comparing to the
     * latest list of Content results on Reddit's side under the specified
     * Profile Content Group.
     *
     * @param  Context  $context
     * @param  string  $profileGroup
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function refreshPendingEntitiesByProfileGroup(Context $context, string $profileGroup): void
    {
        $moreContentsAvailable = true;
        $after = '';
        while ($moreContentsAvailable) {
            $savedContents = $this->redditApi->getSavedContents($context, limit: self::BATCH_SIZE, after: $after);

            if (!empty($savedContents['children'])) {
                $this->addContentsToPendingSync($context, $savedContents['children']);
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
     * @param  Context  $context
     * @param  array  $contentsData
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function addContentsToPendingSync(Context $context, array $contentsData): void
    {
        $pendingSyncParents = [];
        $persistedCount = 0;
        foreach ($contentsData as $contentData) {
            $kind = $contentData['kind'];
            $fullRedditId = $this->fullRedditIdHelper->formatFullRedditId($kind, $contentData['data']['id']);

            $syncedContent = $this->contentRepository->findOneBy(['fullRedditId' => $fullRedditId]);
            $existingPendingContent = $this->contentPendingSyncRepository->findOneBy(['fullRedditId' => $fullRedditId]);

            if (empty($syncedContent) && empty($existingPendingContent)) {
                $pendingSync = new ContentPendingSync();
                $pendingSync->setJsonData(json_encode($contentData));
                $pendingSync->setFullRedditId($fullRedditId);

                $savedGroup = $this->profileContentGroupRepository->getGroupByName(ProfileContentGroup::PROFILE_GROUP_SAVED);
                $pendingSync->setProfileContentGroup($savedGroup);

                $this->entityManager->persist($pendingSync);

                if ($kind === Kind::KIND_COMMENT) {
                    if (!isset($pendingSyncParents[$contentData['data']['link_id']])) {
                        $pendingSyncParents[$contentData['data']['link_id']] = [];
                    }

                    $pendingSyncParents[$contentData['data']['link_id']][] = $fullRedditId;
                }

                $persistedCount++;
                if (($persistedCount % self::PERSISTENCE_BATCH_SIZE) === 0) {
                    $this->entityManager->flush();
                    $persistedCount = 0;
                }
            }
        }

        if ($persistedCount > 0) {
            $this->entityManager->flush();
        }
        $this->entityManager->clear();

        $this->appendPendingSyncParents($context, $pendingSyncParents);
    }

    /**
     * Loop through the provided array of parent Reddit IDs, retrieve their
     * Content data, and append to their respective pending sync Entity.
     *
     * @param  Context  $context
     * @param  array  $pendingSyncParents
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function appendPendingSyncParents(Context $context, array $pendingSyncParents = []): void
    {
        if (empty($pendingSyncParents)) {
            return;
        }

        $parentRedditIds = array_keys($pendingSyncParents);

        $parentItemJsons = $this->itemsService->getItemInfoByRedditIds($context, $parentRedditIds);

        $persistedCount = 0;
        foreach ($parentItemJsons as $parentItemJson) {
            $redditIds = $pendingSyncParents[$parentItemJson->getRedditId()];
            $pendingSyncEntities = $this->contentPendingSyncRepository->findPendingSyncsByRedditIds($redditIds);

            foreach ($pendingSyncEntities as $pendingSync) {
                $pendingSync->setParentJsonData($parentItemJson->getJsonBody());
                $this->entityManager->persist($pendingSync);

                $persistedCount++;
                if (($persistedCount % self::PERSISTENCE_BATCH_SIZE) === 0) {
                    $this->entityManager->flush();
                    $persistedCount = 0;
                }
            }
        }

        if ($persistedCount > 0) {
            $this->entityManager->flush();
        }

        $this->entityManager->clear();
    }
}
