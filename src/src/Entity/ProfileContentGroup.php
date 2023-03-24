<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProfileContentGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileContentGroupRepository::class)]
class ProfileContentGroup
{
    const PROFILE_GROUP_SAVED = 'saved';

    const PROFILE_GROUP_SUBMITTED = 'submitted';

    const PROFILE_GROUP_COMMENTS = 'comments';

    const PROFILE_GROUP_UPVOTED = 'upvoted';

    const PROFILE_GROUP_DOWNVOTED = 'downvoted';

    const PROFILE_GROUP_GILDED = 'gilded';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 50)]
    private $groupName;

    #[ORM\Column(type: 'string', length: 100)]
    private $displayName;

    #[ORM\OneToMany(mappedBy: 'profileContentGroup', targetEntity: ContentPendingSync::class, orphanRemoval: true)]
    private $contentsPendingSync;

    public function __construct()
    {
        $this->contentsPendingSync = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function setGroupName(string $groupName): self
    {
        $this->groupName = $groupName;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * @return Collection<int, ContentPendingSync>
     */
    public function getContentsPendingSync(): Collection
    {
        return $this->contentsPendingSync;
    }

    public function addContentsPendingSync(ContentPendingSync $contentsPendingSync): self
    {
        if (!$this->contentsPendingSync->contains($contentsPendingSync)) {
            $this->contentsPendingSync[] = $contentsPendingSync;
            $contentsPendingSync->setProfileContentGroup($this);
        }

        return $this;
    }

    public function removeContentsPendingSync(ContentPendingSync $contentsPendingSync): self
    {
        if ($this->contentsPendingSync->removeElement($contentsPendingSync)) {
            // set the owning side to null (unless already changed)
            if ($contentsPendingSync->getProfileContentGroup() === $this) {
                $contentsPendingSync->setProfileContentGroup(null);
            }
        }

        return $this;
    }
}
