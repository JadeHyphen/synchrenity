<?php
// tests/CacheManagerTest.php
use Synchrenity\Cache\SynchrenityCacheManager;
use Synchrenity\Audit\SynchrenityAuditTrail;

date_default_timezone_set('UTC');
require_once __DIR__ . '/../lib/Cache/SynchrenityCacheManager.php';
require_once __DIR__ . '/../lib/Audit/SynchrenityAuditTrail.php';

function assertEqual($a, $b, $msg = '') {
    if ($a !== $b) {
        echo "FAIL: $msg\nExpected: ".var_export($b, true)."\nGot: ".var_export($a, true)."\n";
    } else {
        echo "PASS: $msg\n";
    }
}

$audit = new SynchrenityAuditTrail(__DIR__ . '/cache_test_audit.log');

// Test memory backend
$cache = new SynchrenityCacheManager('memory');
$cache->setAuditTrail($audit);

$cache->set('test', 'value', 2);
assertEqual($cache->get('test'), 'value', 'Memory: set/get');

sleep(3);
assertEqual($cache->get('test'), null, 'Memory: TTL expiration');

$cache->set('foo', 'bar');
$cache->delete('foo');
assertEqual($cache->get('foo'), null, 'Memory: delete');

$cache->set('alpha', 'beta');
assertEqual($cache->exists('alpha'), true, 'Memory: exists true');
$cache->clear();
assertEqual($cache->exists('alpha'), false, 'Memory: exists false after clear');

// Test file backend
$fileCache = new SynchrenityCacheManager('file', ['filePath' => __DIR__ . '/test_cache.data']);
$fileCache->setAuditTrail($audit);
$fileCache->set('persist', 'yes', 2);
assertEqual($fileCache->get('persist'), 'yes', 'File: set/get');
sleep(3);
assertEqual($fileCache->get('persist'), null, 'File: TTL expiration');
$fileCache->set('foo', 'bar');
$fileCache->delete('foo');
assertEqual($fileCache->get('foo'), null, 'File: delete');
$fileCache->set('alpha', 'beta');
assertEqual($fileCache->exists('alpha'), true, 'File: exists true');
$fileCache->clear();
assertEqual($fileCache->exists('alpha'), false, 'File: exists false after clear');

// Audit log check
$auditLogs = $audit->getLogs(10);
assertEqual(is_array($auditLogs), true, 'Audit: getLogs returns array');

echo "\nAll cache manager tests completed.\n";
