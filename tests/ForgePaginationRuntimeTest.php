<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use Synchrenity\Pagination\SynchrenityPaginator;
use Synchrenity\Forge\Components\ForgePagination;

require_once __DIR__ . '/../lib/Pagination/SynchrenityPaginator.php';
require_once __DIR__ . '/../lib/Forge/Components/ForgePagination.php';

class ForgePaginationRuntimeTest extends TestCase
{
    public function testPaginationRendersHtml()
    {
        $data = range(1, 100);
        $page = 2;
        $perPage = 10;
        $route = '/test-pagination';
        $params = ['foo' => 'bar'];
        $paginator = new SynchrenityPaginator($data, count($data), $page, $perPage, $route, $params);
        $html = ForgePagination::render($paginator, ['seo' => true]);
        $this->assertStringContainsString('pagination-nav', $html, 'Pagination HTML should contain navigation');
    }
}