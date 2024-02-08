<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\Pagination\Paginator;

class Pagination
{
    /**
     * This number indicates the number of page numbers that should surface
     * before and after the current page number.
     *
     * For example, for a buffer of 5 and a current page number of 20, the
     * previous and next page numbers should be:
     * 15, 16, 17, 18, 19, 20 (current), 21, 22, 23, 24, 25
     *
     * @var int
     */
    const PAGE_NUMBER_LIST_BUFFER = 5;

    /**
     * Initialize and return a new Paginator based on the provided items data
     * and configuration parameters.
     *
     * @param  int  $totalItems
     * @param  int  $itemsPerPage
     * @param  int  $currentPage
     * @param  string  $uriPath Target URI path, excluding query parameters.
     * @param  array  $queryParams Query parameters formatted as paramName => paramValue
     *
     * @return Paginator
     */
    public function createNewPaginator(int $totalItems, int $itemsPerPage, int $currentPage, string $uriPath, array $queryParams = []): Paginator
    {
        $paginator = new Paginator();
        $paginator->setCurrentPage($currentPage);
        $paginator->setItemsPerPage($itemsPerPage);
        $paginator->setTotalPages(
            $this->calculateTotalPages($totalItems, $itemsPerPage)
        );

        $previousPageNumbers = $this->calculatePreviousPageNumbersSet($currentPage);
        // If page 1 is not included in the previous set, enable the "First"
        // page link.
        if (!empty($previousPageNumbers)
            && in_array(1, $previousPageNumbers) === false
        ) {
            $paginator->setFirstPageLinkEnabled(true);
        }

        $nextPageNumbers = $this->calculateNextPageNumbersSet($currentPage, $paginator->getTotalPages());
        // If the last page is not included in the next set, enable the "Last"
        // page link.
        if (!empty($nextPageNumbers) && in_array($paginator->getTotalPages(), $nextPageNumbers) === false) {
            $paginator->setLastPageLinkEnabled(true);
        }

        $paginator->setPageNumbers([
            ...$previousPageNumbers,
            $currentPage,
            ...$nextPageNumbers,
        ]);

        $paginator->setUriPath($uriPath, $queryParams);

        return $paginator;
    }

    /**
     * Calculate the total number of pages needed to accommodate the provided
     * total number of items in the designated batch size.
     *
     * @param  int  $totalItems
     * @param  int  $itemsPerPage
     *
     * @return int
     */
    private function calculateTotalPages(int $totalItems, int $itemsPerPage): int
    {
        return (int) ceil($totalItems/$itemsPerPage);
    }

    /**
     * Calculate the page numbers that should surface before the current page.
     *
     * @param  int  $currentPage
     *
     * @return array
     */
    private function calculatePreviousPageNumbersSet(int $currentPage): array
    {
        $previous = [];
        $linkCount = 0;
        while ($currentPage > 1 && $linkCount < self::PAGE_NUMBER_LIST_BUFFER) {
            $currentPage -= 1;
            $previous[] = $currentPage;

            $linkCount++;
        }

        return array_reverse($previous);
    }

    /**
     * Calculate the page numbers that should surface after the current page.
     *
     * @param  int  $currentPage
     * @param  int  $totalPages
     *
     * @return array
     */
    private function calculateNextPageNumbersSet(int $currentPage, int $totalPages): array
    {
        $next = [];
        $linkCount = 0;
        while ($currentPage < $totalPages && $linkCount < self::PAGE_NUMBER_LIST_BUFFER) {
            $currentPage += 1;
            $next[] = $currentPage;

            $linkCount++;
        }

        return $next;
    }
}
