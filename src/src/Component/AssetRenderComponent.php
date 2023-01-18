<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\AssetInterface;
use App\Service\Reddit\Manager\Assets;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('asset_render')]
class AssetRenderComponent extends AbstractController
{
    public AssetInterface $asset;

    public bool $linkImage = false;

    public function __construct(private readonly Assets $assetsManager)
    {
    }

    public function getPath(): string
    {
        return $this->assetsManager->getAssetPath($this->asset);
    }
}
