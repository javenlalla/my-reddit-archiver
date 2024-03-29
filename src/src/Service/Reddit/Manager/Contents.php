<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager;

use App\Denormalizer\ContentDenormalizer;
use App\Entity\Content;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Items;
use Psr\Cache\InvalidArgumentException;

class Contents
{
    public function __construct(
        private readonly ContentDenormalizer $contentDenormalizer,
        private readonly Items $itemsService,
    ) {
    }

    /**
     * Parse and prepare the provided Content data (Link Post or Comment) and
     * denormalize the data into a Content Entity to return.
     *
     * @param  Context  $apiContext
     * @param  array  $contentRawData
     * @param  array  $context
     *
     * @return Content
     * @throws InvalidArgumentException
     */
    public function parseAndDenormalizeContent(Context $apiContext, array $contentRawData, array $context = []): Content
    {
        if ($contentRawData['kind'] === 'Listing') {
            $contentRawData = $contentRawData['data']['children'][0];
        }

        if (!empty($contentRawData['data']['crosspost_parent_list']) && !empty($contentRawData['data']['crosspost_parent'])) {
            $crosspostData = $this->itemsService->getItemInfoByRedditId($apiContext, $contentRawData['data']['crosspost_parent'])->getJsonBodyArray();

            if (!empty($crosspostData['data'])) {
                $context['crosspost'] = $crosspostData;
            }
        }

        return $this->contentDenormalizer->denormalize($contentRawData, Content::class, null, $context);
    }
}