<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Service\Reddit\Api;
use App\Service\Reddit\Api\Context;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RateLimiterTest extends KernelTestCase
{
    private Api $redditApi;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->redditApi = $container->get(Api::class);
    }

    /**
     * Verify calls to Reddit API are being rate limited correctly.
     *
     * @return void
     */
    public function testRateLimiting(): void
    {
        $context = new Context('RateLimiterTest:testRateLimiting');

        for ($i = 1; $i < 61; $i++) {
            $time = time();
            $this->redditApi->getRedditItemInfoById($context, 't3_vepbt0');
            $processTime = time();

            // Verify the 61st request is rate limited.
            if ($i === 61) {
                $this->assertGreaterThan(60, ($processTime - $time), sprintf('Processing was not rate limited for request %d.', $i));
            } else {
                $this->assertLessThan(60, ($processTime - $time), sprintf('Processing took too long for request %d.', $i));
            }
        }
    }
}
