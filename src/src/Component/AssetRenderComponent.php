<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\Asset;
use App\Service\Reddit\Manager\Assets;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('asset_render')]
class AssetRenderComponent extends AbstractController
{
    public Asset $asset;

    public ?Asset $thumbnailAsset = null;

    public bool $linkImage = false;

    public string $customClass = '';

    public function __construct(private readonly Assets $assetsManager)
    {
    }

    public function getPath(): string
    {
        if ($this->asset->isDownloaded()) {
            return $this->assetsManager->getAssetPath($this->asset);
        }

        return $this->asset->getSourceUrl();
    }

    public function getIsVideo(): bool
    {
        return str_contains($this->asset->getFilename(), '.mp4');
    }

    public function getVideoPoster(): string
    {
        if ($this->thumbnailAsset instanceof Asset) {
            return $this->assetsManager->getAssetPath($this->thumbnailAsset);
        }

        return '';
    }
}
