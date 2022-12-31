<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Content;
use App\Normalizer\ContentNormalizer;
use App\Repository\ContentRepository;
use App\Service\Typesense\Search as TsSearch;

class Search
{
    public function __construct(
        private readonly ContentRepository $contentRepository,
        private readonly ContentNormalizer $contentNormalizer,
        private readonly TsSearch $tsSearch,
    ) {
    }

    public function search(?string $searchQuery, array $subreddits = [], array $flairTexts = []): array
    {
        $contents = [];
        if (empty($searchQuery)) {
            $contents = $this->contentRepository->findAll();
        } else {
            $searchResults = $this->tsSearch->search($searchQuery, $subreddits, $flairTexts);

            if ($searchResults['found'] > 0) {
                foreach ($searchResults['hits'] as $hit) {
                    $contentId = (int) $hit['document']['id'];

                    $content = $this->contentRepository->find($contentId);
                    if ($content instanceof Content) {
                        $contents[] = $content;
                    }
                }
            }
        }

        $contentsNormalized = [];
        foreach ($contents as $content) {
            $contentsNormalized[] = $this->contentNormalizer->normalize($content);
        }

        return $contentsNormalized;
    }
}
