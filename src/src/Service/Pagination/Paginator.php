<?php
declare(strict_types=1);

namespace App\Service\Pagination;

class Paginator
{
    /** @var string */
    const PAGE_QUERY_PARAM_NAME = 'page';

    /** @var int */
    private int $totalPages = 1;

    /** @var int */
    private int $itemsPerPage = 1;

    /** @var int */
    private int $currentPage = 1;

    /** @var int[] */
    private array $pageNumbers = [];

    /** @var bool */
    private bool $firstPageLinkEnabled = false;

    /** @var bool  */
    private bool $lastPageLinkEnabled = false;

    /**
     * URI path with any expected query parameters that will be used when
     * constructing pagination links.
     *
     * @var string
     */
    private string $uriPath = '';

    /**
     * Convenience flag to quickly check if the current URI path has a query
     * string attached (true) or not (false).
     *
     * @var bool
     */
    private bool $hasQueryParams = false;

    /**
     * @return int
     */
    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * @param  int  $totalPages
     */
    public function setTotalPages(int $totalPages): void
    {
        $this->totalPages = $totalPages;
    }

    /**
     * @return int
     */
    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    /**
     * @param  int  $itemsPerPage
     */
    public function setItemsPerPage(int $itemsPerPage): void
    {
        $this->itemsPerPage = $itemsPerPage;
    }

    /**
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * @param  int  $currentPage
     */
    public function setCurrentPage(int $currentPage): void
    {
        $this->currentPage = $currentPage;
    }

    /**
     * @return array
     */
    public function getPageNumbers(): array
    {
        return $this->pageNumbers;
    }

    /**
     * @param  array  $pageNumbers
     */
    public function setPageNumbers(array $pageNumbers): void
    {
        $this->pageNumbers = $pageNumbers;
    }

    /**
     * @param  int  $pageNumber
     */
    public function addPageNumber(int $pageNumber): void
    {
        $this->pageNumbers[] = $pageNumber;
    }

    /**
     * @return bool
     */
    public function isFirstPageLinkEnabled(): bool
    {
        return $this->firstPageLinkEnabled;
    }

    /**
     * @param  bool  $firstPageLinkEnabled
     */
    public function setFirstPageLinkEnabled(bool $firstPageLinkEnabled): void
    {
        $this->firstPageLinkEnabled = $firstPageLinkEnabled;
    }

    /**
     * @return bool
     */
    public function isLastPageLinkEnabled(): bool
    {
        return $this->lastPageLinkEnabled;
    }

    /**
     * @param  bool  $lastPageLinkEnabled
     */
    public function setLastPageLinkEnabled(bool $lastPageLinkEnabled): void
    {
        $this->lastPageLinkEnabled = $lastPageLinkEnabled;
    }

    /**
     * Sets the expected Query Params that will be used for constructing
     * pagination links.
     *
     * To avoid conflicts, the `page` key is excluded from being set.
     *
     * When setting the parameter values, the values are URL-encoded.
     *
     * @param  string  $uriPath Target URI path, excluding query parameters.
     * @param  array  $queryParams Expected Query Params formatted as paramName => paramValue
     *
     * @return void
     */
    public function setUriPath(string $uriPath, array $queryParams = []):void
    {
        $this->uriPath = $uriPath;

        if (!empty($queryParams)) {
            $queryPieces = [];
            foreach ($queryParams as $paramName => $paramValue) {
                if ($paramName !== self::PAGE_QUERY_PARAM_NAME) {
                    if (is_array($paramValue) === true) {
                        $arrayParamName = urlencode(sprintf('%s[]', $paramName));

                        foreach ($paramValue as $paramArrayValue) {
                            $queryPieces[] = sprintf('%s=%s', $arrayParamName, urlencode($paramArrayValue));
                        }
                    } else {
                        $queryPieces[] = sprintf('%s=%s', $paramName, urlencode($paramValue));
                    }
                }
            }

            $queryString = implode('&', $queryPieces);

            $this->uriPath = sprintf('%s?%s', $uriPath, $queryString);
            $this->hasQueryParams = true;
        }
    }

    /**
     * Construct a pagination link using the currently set URI path and query
     * string.
     *
     * The specified page is appended to the query string.
     *
     * @param  int  $page
     *
     * @return string
     */
    public function paginationLink(int $page): string {
        if ($this->hasQueryParams === true) {
            return sprintf('%s&page=%d', $this->uriPath, $page);
        }

        return sprintf('%s?page=%d', $this->uriPath, $page);
    }
}
