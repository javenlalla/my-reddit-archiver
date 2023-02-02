<?php
declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\Content;
use App\Service\Search;

class Results
{
    private int $perPage = Search::DEFAULT_LIMIT;

    private int $page = 1;

    private int $total = 0;

    /**
     * @var Content[]
     */
    private array $results = [];

    /**
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @param  int  $perPage
     */
    public function setPerPage(int $perPage): void
    {
        $this->perPage = $perPage;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @param  int  $page
     */
    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @param  int  $total
     */
    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    /**
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @param  array  $results
     */
    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return count($this->results);
    }
}
