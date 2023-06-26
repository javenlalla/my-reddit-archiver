<?php
declare(strict_types=1);

namespace App\Tests\unit\Helper;

use App\Entity\Kind;
use App\Helper\RedditIdHelper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RedditIdHelperTest extends KernelTestCase
{
    private RedditIdHelper $redditIdHelper;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->redditIdHelper = $container->get(RedditIdHelper::class);
    }

    /**
     * Verify the Reddit ID can be correctly extracted from a given Reddit URL.
     *
     * @return void
     */
    public function testExtractRedditIdFromUrl(): void
    {
        $targetRedditId = 't3_vepbt0';
        $urls = [
            'https://www.reddit.com/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/',
            '/r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/',
            'r/shittyfoodporn/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf',
            '/comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf',
            'comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf',
            'comments/vepbt0/my_sisterinlaw_made_vegetarian_meat_loaf/teststring',
            // Unicode test.
            'comments/vepbt0/üäüä_my_sisterinlaw_made_üä_üä_vegetarian_meat_loaf_üä/teststring/test_string',
        ];

        foreach ($urls as $url) {
            $redditId = $this->redditIdHelper->extractRedditIdFromUrl(Kind::KIND_LINK, $url);
            $this->assertEquals($targetRedditId, $redditId);
        }

        $targetRedditId = 't3_10zrjou';
        $targetCommentRedditId = 't1_j84z4vm';
        $urls = [
            'https://www.reddit.com/r/TheSilphRoad/comments/10zrjou/my_new_stunlock_smeargle/j84z4vm/',
            '/r/TheSilphRoad/comments/10zrjou/my_new_stunlock_smeargle/j84z4vm',
            'r/TheSilphRoad/comments/10zrjou/my_new_stunlock_smeargle/j84z4vm/',
            '/comments/10zrjou/my_new_stunlock_smeargle/j84z4vm',
            'comments/10zrjou/my_new_stunlock_smeargle/j84z4vm/teststring',
            'comments/10zrjou/my_new_stunlock_smeargle/j84z4vm/teststring/test_string',
            'comments/10zrjou/üä_üä_my_new_stunlock_üä_smeargle_üä_üä/j84z4vm/teststring/test_string',
        ];

        foreach ($urls as $url) {
            $redditId = $this->redditIdHelper->extractRedditIdFromUrl(Kind::KIND_LINK, $url);
            $this->assertEquals($targetRedditId, $redditId);

            $redditId = $this->redditIdHelper->extractRedditIdFromUrl(Kind::KIND_COMMENT, $url);
            $this->assertEquals($targetCommentRedditId, $redditId);
        }

        https://www.reddit.com/r/arbeitsleben/comments/14j10nk/ist_das_zul%C3%A4ssig/
    }
}
