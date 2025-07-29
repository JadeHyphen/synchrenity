<?php

declare(strict_types=1);

namespace Synchrenity\Testing;

// SynchrenityTestUtils: Robust test helpers for HTTP, templates, DB, and assertions
class SynchrenityTestUtils
{
    /** Simple class method stubber (returns a closure that overrides a method) */
    public static function stubMethod($object, $method, $fn)
    {
        $ref  = new \ReflectionClass($object);
        $prop = $ref->getProperty($method);
        $prop->setAccessible(true);
        $prop->setValue($object, \Closure::fromCallable($fn));
    }

    /** Freeze time for time-dependent tests */
    public static function freezeTime($datetime)
    {
        \date_default_timezone_set('UTC');
        \putenv('SYNCHRENITY_TEST_TIME=' . $datetime);
    }
    public static function unfreezeTime()
    {
        \putenv('SYNCHRENITY_TEST_TIME');
    }

    /** Record and replay HTTP requests (integration test helper) */
    public static function recordHttp($method, $uri, $params = [], $headers = [], $server = [], $recordFile = null)
    {
        $output = self::httpRequest($method, $uri, $params, $headers, $server);

        if ($recordFile) {
            file_put_contents($recordFile, $output);
        }

        return $output;
    }
    public static function replayHttp($recordFile)
    {
        return file_get_contents($recordFile);
    }

    /** Seed database with data for tests */
    public static function seedDb($pdo, $table, array $rows)
    {
        foreach ($rows as $row) {
            $cols = implode(',', array_keys($row));
            $vals = implode(',', array_fill(0, count($row), '?'));
            $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($vals)");
            $stmt->execute(array_values($row));
        }
    }

    /** Snapshot testing: compare output to stored snapshot */
    public static function assertSnapshot($output, $snapshotFile)
    {
        if (!file_exists($snapshotFile)) {
            file_put_contents($snapshotFile, $output);

            return;
        }
        $expected = file_get_contents($snapshotFile);

        if ($output !== $expected) {
            throw new \Exception("Snapshot does not match.\nExpected:\n$expected\nActual:\n$output");
        }
    }

    /** Register a custom assertion */
    protected static $customAssertions = [];
    public static function registerAssertion($name, callable $fn)
    {
        self::$customAssertions[$name] = $fn;
    }
    public static function __callStatic($name, $args)
    {
        if (isset(self::$customAssertions[$name])) {
            return call_user_func_array(self::$customAssertions[$name], $args);
        }

        throw new \BadMethodCallException("No such assertion: $name");
    }

    /** Mark code as covered for test coverage tools */
    public static function cover($name)
    {
        if (function_exists('xdebug_start_code_coverage')) {
            // No-op, coverage is handled by PHPUnit/Xdebug
        }
    }

    /** CLI command testing utility */
    public static function runCli($args)
    {
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../../../synchrenity') . ' ' . implode(' ', array_map('escapeshellarg', $args));

        return shell_exec($cmd);
    }
    /** Simulate an HTTP request and return response */
    public static function httpRequest($method, $uri, $params = [], $headers = [], $server = [])
    {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI'    => $uri,
        ], $server);
        $_GET  = $method === 'GET' ? $params : [];
        $_POST = $method === 'POST' ? $params : [];

        foreach ($headers as $k => $v) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }
        ob_start();
        include __DIR__ . '/../../../public/index.php';
        $output = ob_get_clean();

        return $output;
    }

    /** Assert that a string is in the response */
    public static function assertSee($needle, $haystack, $msg = '')
    {
        if (strpos($haystack, $needle) === false) {
            throw new \Exception($msg ?: "Failed asserting that response contains '$needle'.");
        }
    }

    /** Assert that a template/view was rendered (by marker or content) */
    public static function assertTemplateRendered($template, $output, $msg = '')
    {
        if (strpos($output, $template) === false) {
            throw new \Exception($msg ?: "Template '$template' was not rendered.");
        }
    }

    /** Begin a DB transaction for test isolation */
    public static function beginTransaction($pdo)
    {
        $pdo->beginTransaction();
    }

    /** Rollback a DB transaction after test */
    public static function rollback($pdo)
    {
        $pdo->rollBack();
    }

    /** Capture output of a callable */
    public static function captureOutput(callable $fn)
    {
        ob_start();
        $fn();

        return ob_get_clean();
    }

    /** Assert response is JSON and decode */
    public static function assertJson($output, &$decoded = null, $msg = '')
    {
        $decoded = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception($msg ?: 'Response is not valid JSON.');
        }
    }

    /** Assert HTTP status code in output (if present) */
    public static function assertStatus($output, $expected, $msg = '')
    {
        if (!preg_match('/HTTP\/\d\.\d (\d{3})/', $output, $m) || $m[1] != $expected) {
            throw new \Exception($msg ?: "Expected status $expected, got " . ($m[1] ?? 'none'));
        }
    }

    /** Assert header in output (if present) */
    public static function assertHeader($output, $header, $msg = '')
    {
        if (stripos($output, $header) === false) {
            throw new \Exception($msg ?: "Header '$header' not found in response.");
        }
    }
}
