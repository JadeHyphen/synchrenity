<?php
namespace Synchrenity\Tenant;

class SynchrenityTenantManager {
    protected $auditTrail;
    protected $tenants = [];
    protected $currentTenant = null;
    protected $hooks = [];

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }

    // Register a tenant (metadata: name, config, etc.)
    public function register($tenantId, $meta = []) {
        $this->tenants[$tenantId] = $meta;
        $this->audit('register_tenant', null, ['tenant_id' => $tenantId, 'meta' => $meta]);
    }

    // List all tenants
    public function listTenants() {
        return array_keys($this->tenants);
    }

    // Get tenant metadata
    public function getTenant($tenantId) {
        return $this->tenants[$tenantId] ?? null;
    }

    // Switch active tenant
    public function switchTenant($tenantId) {
        if (!isset($this->tenants[$tenantId])) {
            $this->audit('switch_failed', null, ['tenant_id' => $tenantId, 'reason' => 'not_registered']);
            return false;
        }
        $this->currentTenant = $tenantId;
        foreach ($this->hooks as $hook) {
            call_user_func($hook, $tenantId, $this->tenants[$tenantId]);
        }
        $this->audit('switch_tenant', null, ['tenant_id' => $tenantId, 'meta' => $this->tenants[$tenantId]]);
        return true;
    }

    // Get current tenant
    public function getCurrentTenant() {
        return $this->currentTenant;
    }

    // Add custom hook (e.g., billing, limits)
    public function addHook(callable $hook) {
        $this->hooks[] = $hook;
    }

    // Audit helper
    protected function audit($action, $userId = null, $meta = []) {
        if ($this->auditTrail) {
            $this->auditTrail->log($action, [], $userId, $meta);
        }
    }
}
