<?php
declare(strict_types=1);

namespace App\Helper;

use App\Entity\Content;
use App\Entity\Post;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class YoutubeVideoHelper
{
    const EMBED_URL_FORMAT = 'https://www.youtube.com/embed/%s?autoplay=1&origin=%s';

    /**
     * Regex to use when searching for the Video ID within a YouTube watch URL.
     * Example: https://www.youtube.com/watch?v=aeXd9fFtBW8&t => aeXd9fFtBW8
     */
    const WATCH_URL_VIDEO_ID_REGEX = '/watch\?v=([a-z0-9]*)/i';

    /**
     * Array of expected YouTube domains.
     *
     * @var string[]
     */
    private $youtubeDomains = [
        'youtube.com',
        'youtu.be',
    ];

    public function __construct(private readonly UrlGeneratorInterface $router)
    {
    }

    /**
     * Determine if the provided Content contains a Post which links to a
     * YouTube video.
     *
     * @param  Content  $content
     *
     * @return bool
     */
    public function isContentYoutubeVideo(Content $content): bool
    {
        return $this->isYoutubeDomain($content->getPost()->getUrl());
    }

    /**
     * Determine if the provided Post links to a YouTube video.
     *
     * @param  Post  $post
     *
     * @return bool
     */
    public function isPostYoutubeVideo(Post $post): bool
    {
        return $this->isYoutubeDomain($post->getUrl());
    }

    /**
     * Determine if the provided URL is of a YouTube domain.
     *
     * @param  string|null  $url
     *
     * @return bool
     */
    public function isYoutubeDomain(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $isYoutubeDomain = false;
        foreach ($this->youtubeDomains as $domain) {
            if (str_contains($url, $domain)) {
                $isYoutubeDomain = true;
            }
        }

        return $isYoutubeDomain;
    }

    /**
     * Generate the embed URL for the YouTube video associated to the provided
     * Content.
     *
     * @param  int  $contentId
     * @param  string  $ytUrl
     *
     * @return string
     */
    public function generateEmbedUrl(int $contentId, string $ytUrl): string
    {
        $videoId = $this->getVideoIdFromWatchLink($ytUrl);

        $link = $this->router->generate(
            'contents_view_content',
            ['id' => $contentId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return sprintf(self::EMBED_URL_FORMAT, $videoId, $link);
    }

    /**
     * Given a YouTube `watch` (see example below) URL, extract the Video ID from
     * the URL.
     *
     * Example: https://www.youtube.com/watch?v=aeXd9fFtBW8
     *
     * @param  string  $watchUrl
     *
     * @return string
     */
    public function getVideoIdFromWatchLink(string $watchUrl): string
    {
        preg_match(self::WATCH_URL_VIDEO_ID_REGEX, $watchUrl, $urlParts);
        if (!empty($urlParts[1])) {
            return $urlParts[1];
        }

        return '';
    }
}
