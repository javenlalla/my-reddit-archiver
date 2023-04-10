<?php
declare(strict_types=1);

namespace App\Tests\unit\Helper;

use App\Helper\YoutubeVideoHelper;
use App\Repository\ContentRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class YoutubeVideoHelperTest extends KernelTestCase
{
    private YoutubeVideoHelper $ytVideoHelper;

    private ContentRepository $contentRepository;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->ytVideoHelper = $container->get(YoutubeVideoHelper::class);
        $this->contentRepository = $container->get(ContentRepository::class);
    }

    /**
     * Test generating an embed URL for a Content that links to a YouTube
     * video.
     *
     * @return void
     */
    public function testGenerateEmbedUrl(): void
    {
        $ytUrl = 'https://www.youtube.com/watch?v=aeXd9fFtBW8&amp;t';

        $content = $this->contentRepository->findOneBy(['fullRedditId' => 't3_x00006']);

        $videoId = $this->ytVideoHelper->getVideoIdFromWatchLink($ytUrl);
        $this->assertEquals('aeXd9fFtBW8', $videoId);

        $embedUrl = $this->ytVideoHelper->generateEmbedUrl($content->getId(), $ytUrl);
        $this->assertEquals('https://www.youtube.com/embed/aeXd9fFtBW8?autoplay=1&origin=http://localhost/contents/view/6', $embedUrl);
    }
}
