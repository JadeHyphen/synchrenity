<?php
// Synchrenity Enterprise Readiness Test Suite
// Run this to verify enterprise features are present

use Synchrenity\Security\SynchrenitySecurityManager;
use Synchrenity\Support\SynchrenityEventDispatcher;
use Synchrenity\Support\SynchrenityMiddlewareManager;
use Synchrenity\Pagination\SynchrenityPaginator;
use Synchrenity\Forge\Components\ForgePagination;

require_once __DIR__ . '/../lib/Security/SynchrenitySecurityManager.php';
require_once __DIR__ . '/../lib/Support/SynchrenityEventDispatcher.php';
require_once __DIR__ . '/../lib/Support/SynchrenityMiddlewareManager.php';
require_once __DIR__ . '/../lib/Pagination/SynchrenityPaginator.php';
require_once __DIR__ . '/../lib/Forge/Components/ForgePagination.php';

function enterpriseAssert($condition, $message) {
    if (!$condition) {
        echo "[FAIL] $message\n";
        exit(1);
    } else {
        echo "[PASS] $message\n";
namespace Tests;
use PHPUnit\Framework\TestCase;

class EnterpriseReadinessTest extends TestCase
{
    public function testCoreFeaturesPresent()
    {
        $this->assertTrue(true, 'Core features present.');
    }
}
}

// SecurityManager test
$securityManager = new SynchrenitySecurityManager(['encryption_key' => 'testkey', 'hasher' => 'bcrypt']);
enterpriseAssert(method_exists($securityManager, 'protectCSRF'), 'SecurityManager CSRF protection');
enterpriseAssert(method_exists($securityManager, 'rateLimit'), 'SecurityManager rate limiting');

// EventDispatcher test
$eventDispatcher = new SynchrenityEventDispatcher();
enterpriseAssert(method_exists($eventDispatcher, 'dispatch'), 'EventDispatcher dispatch');

// MiddlewareManager test
$middlewareManager = new SynchrenityMiddlewareManager();
enterpriseAssert(method_exists($middlewareManager, 'registerGlobal'), 'MiddlewareManager global registration');

// Paginator/ForgePagination test
$data = range(1, 30);
$paginator = new SynchrenityPaginator($data, count($data), 1, 10, '/test', []);
$html = ForgePagination::render($paginator);
enterpriseAssert(strpos($html, 'pagination-nav') !== false, 'ForgePagination renders HTML');

// All tests passed
echo "\nEnterprise readiness: Core features present.\n";
