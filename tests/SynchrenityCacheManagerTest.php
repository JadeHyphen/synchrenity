<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use Synchrenity\Cache\SynchrenityCacheManager;
use Synchrenity\Audit\SynchrenityAuditTrail;

class SynchrenityCacheManagerTest extends TestCase {
    protected $audit;
    protected $cache;
    protected $fileCache;

    protected function setUp(): void {
        date_default_timezone_set('UTC');
        $this->audit = new SynchrenityAuditTrail(__DIR__ . '/cache_test_audit.log');
        $this->cache = new SynchrenityCacheManager('memory');
        $this->cache->setAuditTrail($this->audit);
        $this->fileCache = new SynchrenityCacheManager('file', ['filePath' => __DIR__ . '/test_cache.data']);
        $this->fileCache->setAuditTrail($this->audit);
    }

    public function testMemorySetGet() {
        $this->cache->set('test', 'value', 2);
        $this->assertEquals('value', $this->cache->get('test'), 'Memory: set/get');
    }

    public function testMemoryTTLExpiration() {
        $this->cache->set('test', 'value', 1);
        sleep(2);
        $this->assertNull($this->cache->get('test'), 'Memory: TTL expiration');
    }

    public function testMemoryDelete() {
        $this->cache->set('foo', 'bar');
        $this->cache->delete('foo');
        $this->assertNull($this->cache->get('foo'), 'Memory: delete');
    }

    public function testMemoryExistsTrueFalse() {
        $this->cache->set('alpha', 'beta');
        $this->assertTrue($this->cache->exists('alpha'), 'Memory: exists true');
        $this->cache->clear();
        $this->assertFalse($this->cache->exists('alpha'), 'Memory: exists false after clear');
    }

    public function testFileSetGet() {
        $this->fileCache->set('persist', 'yes', 2);
        $this->assertEquals('yes', $this->fileCache->get('persist'), 'File: set/get');
    }

    public function testFileTTLExpiration() {
        $this->fileCache->set('persist', 'yes', 1);
        sleep(2);
        $this->assertNull($this->fileCache->get('persist'), 'File: TTL expiration');
    }

    public function testFileDelete() {
        $this->fileCache->set('foo', 'bar');
        $this->fileCache->delete('foo');
        $this->assertNull($this->fileCache->get('foo'), 'File: delete');
    }

    public function testFileExistsTrueFalse() {
        $this->fileCache->set('alpha', 'beta');
        $this->assertTrue($this->fileCache->exists('alpha'), 'File: exists true');
        $this->fileCache->clear();
        $this->assertFalse($this->fileCache->exists('alpha'), 'File: exists false after clear');
    }

    public function testAuditGetLogsReturnsArray() {
        $auditLogs = $this->audit->getLogs(10);
        $this->assertIsArray($auditLogs, 'Audit: getLogs returns array');
    }
}
