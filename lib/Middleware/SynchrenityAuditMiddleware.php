<?php
namespace Synchrenity\Middleware;

/**
 * Auditing middleware: logs requests, responses, and anomalies for compliance and monitoring.
 */
class SynchrenityAuditMiddleware implements SynchrenityMiddlewareInterface {
    protected $logger;
    protected $context = [];
    protected $hooks = [];
    protected $complianceMeta = [];

    public function __construct($logger = null) {
        $this->logger = $logger ?: function($msg) { error_log($msg); };
    }
    public function setContext(array $context) { $this->context = $context; }
    public function before($request) { $this->log('before', $request); $this->triggerHook('before', $request); }
    public function after($request, $response) { $this->log('after', ['request'=>$request,'response'=>$response]); $this->triggerHook('after', $request, $response); }
    public function onError($request, $exception) { $this->log('error', ['request'=>$request,'exception'=>$exception]); $this->triggerHook('error', $request, $exception); return null; }
    public function addHook($event, callable $cb) { $this->hooks[$event][] = $cb; }
    protected function triggerHook($event, ...$args) { foreach ($this->hooks[$event] ?? [] as $cb) call_user_func_array($cb, $args); }
    public function setComplianceMeta(array $meta) { $this->complianceMeta = $meta; }
    public function getComplianceMeta() { return $this->complianceMeta; }
    public function handle($request, callable $next) {
        $this->before($request);
        try {
            $response = $next($request);
            $this->after($request, $response);
            return $response;
        } catch (\Throwable $e) {
            $this->onError($request, $e);
            throw $e;
        }
    }
    protected function log($event, $data) {
        $msg = '['.date('c')."] [$event] ".json_encode($data);
        call_user_func($this->logger, $msg);
    }
}
