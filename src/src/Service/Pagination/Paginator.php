<?php
declare(strict_types=1);

namespace App\Service\Pagination;

class Paginator
{
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
}
