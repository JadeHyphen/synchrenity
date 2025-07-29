<?php

declare(strict_types=1);
// CacheManagerRuntime.php: Demonstration and runtime for SynchrenityCacheManager
use Synchrenity\Audit\SynchrenityAuditTrail;
use Synchrenity\Cache\SynchrenityCacheManager;

require_once __DIR__ . '/../Audit/SynchrenityAuditTrail.php';
require_once __DIR__ . '/SynchrenityCacheManager.php';

// Setup audit trail (file-based)
$audit = new SynchrenityAuditTrail(__DIR__ . '/cache_audit.log');

// Instantiate cache manager (memory backend)
$cache = new SynchrenityCacheManager('memory');
$cache->setAuditTrail($audit);

// Set cache value
$cache->set('foo', 'bar', 2); // TTL 2 seconds
// Get cache value
echo "Value for 'foo': " . $cache->get('foo') . "\n";

// Wait for TTL to expire
sleep(3);
echo "Value for 'foo' after TTL: " . var_export($cache->get('foo'), true) . "\n";

// Set and delete
$cache->set('baz', 'qux');
echo "Value for 'baz': " . $cache->get('baz') . "\n";
$cache->delete('baz');
echo "Value for 'baz' after delete: " . var_export($cache->get('baz'), true) . "\n";

// Clear cache
$cache->set('alpha', 'beta');
$cache->clear();
echo "Value for 'alpha' after clear: " . var_export($cache->get('alpha'), true) . "\n";

// File backend demo
$fileCache = new SynchrenityCacheManager('file', ['filePath' => __DIR__ . '/demo_cache.data']);
$fileCache->setAuditTrail($audit);
$fileCache->set('persist', 'yes', 5);
echo "File cache value for 'persist': " . $fileCache->get('persist') . "\n";

// Show audit log entries
$auditLogs = $audit->getLogs(10);
echo "\nRecent audit logs:\n";

foreach ($auditLogs as $log) {
    echo json_encode($log) . "\n";
}
