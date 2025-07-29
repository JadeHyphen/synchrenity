<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Synchrenity\Pagination\SynchrenityPaginator;
use Synchrenity\Weave\Components\WeavePagination;

require_once __DIR__ . '/../lib/Pagination/SynchrenityPaginator.php';
require_once __DIR__ . '/../lib/Weave/Components/WeavePagination.php';

class WeavePaginationRuntimeTest extends TestCase
{
    public function testPaginationRendersHtml(): void
    {
        $data      = range(1, 100);
        $page      = 2;
        $perPage   = 10;
        $route     = '/test-pagination';
        $params    = ['foo' => 'bar'];
        $paginator = new SynchrenityPaginator($data, count($data), $page, $perPage, $route, $params);
        $html      = WeavePagination::render($paginator, ['seo' => true]);
        $this->assertStringContainsString('pagination-nav', $html, 'Pagination HTML should contain navigation');
    }
}
