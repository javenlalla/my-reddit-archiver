<?php
declare(strict_types=1);

namespace App\Component;

use App\Entity\Content;
use App\Helper\YoutubeVideoHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('youtube_video_render')]
class YoutubeVideoRenderComponent extends AbstractController
{
    public Content $content;

    public function __construct(private readonly YoutubeVideoHelper $ytVideoHelper)
    {
    }

    /**
     * @see YoutubeVideoHelper::isPostYoutubeVideo()
     *
     * @return bool
     */
    public function getIsYoutubeVideo(): bool
    {
        return $this->ytVideoHelper->isContentYoutubeVideo($this->content);
    }

    /**
     * Generate and return the embed URL for the current Content's associated
     * YouTube video.
     *
     * @return string
     */
    public function getEmbedUrl(): string
    {
        return $this->ytVideoHelper->generateEmbedUrl(
            $this->content->getId(),
            $this->content->getPost()->getUrl(),
        );
    }
}
