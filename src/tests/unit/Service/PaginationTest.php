<?php
declare(strict_types=1);

namespace App\Tests\unit\Service;

use App\Service\Pagination;
use App\Service\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group ci-tests
 */
class PaginationTest extends KernelTestCase
{
    private Pagination $paginationService;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->paginationService = $container->get(Pagination::class);
    }

    /**
     * @dataProvider paginatorCreationDataProvider()
     *
     * @return void
     */
    public function testPaginatorCreation(
        int $totalItems,
        int $itemsPerPage,
        int $currentPage,
        int $totalPages,
        array $pageNumbers,
        bool $firstPageLinkEnabled,
        bool $lastPageLinkEnabled
    ) {
        $paginator = $this->paginationService->createNewPaginator($totalItems, $itemsPerPage, $currentPage, '/testing/uri');
        $this->assertInstanceOf(Paginator::class, $paginator);

        $this->assertEquals($totalPages, $paginator->getTotalPages());
        $this->assertEquals($itemsPerPage, $paginator->getItemsPerPage());
        $this->assertEquals($currentPage, $paginator->getCurrentPage());
        $this->assertEquals($pageNumbers, $paginator->getPageNumbers());
        $this->assertEquals($firstPageLinkEnabled, $paginator->isFirstPageLinkEnabled());
        $this->assertEquals($lastPageLinkEnabled, $paginator->isLastPageLinkEnabled());
        $this->assertEquals('/testing/uri?page=2', $paginator->paginationLink(2));
    }

    /**
     * @return array[]
     */
    public function paginatorCreationDataProvider(): array
    {
        return [
            'Simple' => [
                'totalItems' => 5,
                'itemsPerPage' => 1,
                'currentPage' => 1,
                'totalPages' => 5,
                'pageNumbers' => [1,2,3,4,5],
                'firstPageLinkEnabled' => false,
                'lastPageLinkEnabled' => false,
            ],
            'First Page Link Enabled' => [
                'totalItems' => 75,
                'itemsPerPage' => 2,
                'currentPage' => 35,
                'totalPages' => 38,
                'pageNumbers' => [30,31,32,33,34,35,36,37,38],
                'firstPageLinkEnabled' => true,
                'lastPageLinkEnabled' => false,
            ],
            'Last Page Link Enabled' => [
                'totalItems' => 75,
                'itemsPerPage' => 2,
                'currentPage' => 3,
                'totalPages' => 38,
                'pageNumbers' => [1,2,3,4,5,6,7,8],
                'firstPageLinkEnabled' => false,
                'lastPageLinkEnabled' => true,
            ],
            'Both End Links Enabled' => [
                'totalItems' => 75,
                'itemsPerPage' => 2,
                'currentPage' => 20,
                'totalPages' => 38,
                'pageNumbers' => [15,16,17,18,19,20,21,22,23,24,25],
                'firstPageLinkEnabled' => true,
                'lastPageLinkEnabled' => true,
            ],
        ];
    }
}
