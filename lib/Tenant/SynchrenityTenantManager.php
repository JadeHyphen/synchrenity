<?php
namespace Synchrenity\Tenant;

class SynchrenityTenantManager {
    protected $auditTrail;
    protected $tenants = [];
    protected $currentTenant = null;
    protected $hooks = [];
    protected $plugins = [];
    protected $contexts = [];
    protected $events = [];
    // --- ADVANCED: Tenant isolation (per-tenant config, quotas, etc) ---
    protected $tenantConfigs = [];
    protected $tenantQuotas = [];
    protected $tenantBilling = [];

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }

    // Register a tenant (metadata: name, config, etc.)
    public function register($tenantId, $meta = [], $config = [], $quota = [], $billing = []) {
        $this->tenants[$tenantId] = $meta;
        $this->tenantConfigs[$tenantId] = $config;
        $this->tenantQuotas[$tenantId] = $quota;
        $this->tenantBilling[$tenantId] = $billing;
        $this->audit('register_tenant', null, ['tenant_id' => $tenantId, 'meta' => $meta, 'config'=>$config, 'quota'=>$quota, 'billing'=>$billing]);
        $this->triggerEvent('register', $tenantId, $meta);
    }

    // List all tenants
    public function listTenants($filter = null) {
        $ids = array_keys($this->tenants);
        if ($filter && is_callable($filter)) {
            $ids = array_filter($ids, function($id) use ($filter) { return $filter($this->tenants[$id], $id); });
        }
        return array_values($ids);
    }

    // Get tenant metadata
    public function getTenant($tenantId) {
        return $this->tenants[$tenantId] ?? null;
    }
    public function getTenantConfig($tenantId) {
        return $this->tenantConfigs[$tenantId] ?? [];
    }
    public function getTenantQuota($tenantId) {
        return $this->tenantQuotas[$tenantId] ?? [];
    }
    public function getTenantBilling($tenantId) {
        return $this->tenantBilling[$tenantId] ?? [];
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
        $this->triggerEvent('switch', $tenantId, $this->tenants[$tenantId]);
        $this->audit('switch_tenant', null, ['tenant_id' => $tenantId, 'meta' => $this->tenants[$tenantId]]);
        return true;
    }

    // Get current tenant
    public function getCurrentTenant() {
        return $this->currentTenant;
    }
    public function getCurrentTenantConfig() {
        return $this->getTenantConfig($this->currentTenant);
    }
    public function getCurrentTenantQuota() {
        return $this->getTenantQuota($this->currentTenant);
    }
    public function getCurrentTenantBilling() {
        return $this->getTenantBilling($this->currentTenant);
    }

    // Add custom hook (e.g., billing, limits)
    public function addHook(callable $hook) {
        $this->hooks[] = $hook;
    }
    // --- ADVANCED: Plugin support ---
    public function registerPlugin($plugin) {
        if (is_callable([$plugin, 'register'])) $plugin->register($this);
        $this->plugins[] = $plugin;
    }
    // --- ADVANCED: Context per tenant ---
    public function setTenantContext($tenantId, $context) {
        $this->contexts[$tenantId] = $context;
    }
    public function getTenantContext($tenantId) {
        return $this->contexts[$tenantId] ?? [];
    }
    // --- ADVANCED: Event system ---
    public function on($event, callable $cb) {
        $this->events[$event][] = $cb;
    }
    protected function triggerEvent($event, ...$args) {
        foreach ($this->events[$event] ?? [] as $cb) call_user_func_array($cb, $args);
    }
    // --- ADVANCED: Tenant deletion ---
    public function deleteTenant($tenantId) {
        unset($this->tenants[$tenantId], $this->tenantConfigs[$tenantId], $this->tenantQuotas[$tenantId], $this->tenantBilling[$tenantId], $this->contexts[$tenantId]);
        $this->audit('delete_tenant', null, ['tenant_id'=>$tenantId]);
        $this->triggerEvent('delete', $tenantId);
    }
    // --- ADVANCED: Tenant update ---
    public function updateTenant($tenantId, $meta = [], $config = [], $quota = [], $billing = []) {
        if (!isset($this->tenants[$tenantId])) return false;
        $this->tenants[$tenantId] = array_merge($this->tenants[$tenantId], $meta);
        $this->tenantConfigs[$tenantId] = array_merge($this->tenantConfigs[$tenantId] ?? [], $config);
        $this->tenantQuotas[$tenantId] = array_merge($this->tenantQuotas[$tenantId] ?? [], $quota);
        $this->tenantBilling[$tenantId] = array_merge($this->tenantBilling[$tenantId] ?? [], $billing);
        $this->audit('update_tenant', null, ['tenant_id'=>$tenantId,'meta'=>$meta,'config'=>$config,'quota'=>$quota,'billing'=>$billing]);
        $this->triggerEvent('update', $tenantId, $meta, $config, $quota, $billing);
        return true;
    }
    // --- ADVANCED: Tenant search ---
    public function searchTenants(callable $filter) {
        return array_filter($this->tenants, $filter, ARRAY_FILTER_USE_BOTH);
    }
    // --- ADVANCED: Tenant validation ---
    public function validateTenant($tenantId) {
        // Example: check required fields
        $meta = $this->getTenant($tenantId);
        return isset($meta['name']) && isset($meta['email']);
    }
    // --- ADVANCED: Introspection ---
    public function getAllTenants() { return $this->tenants; }
    public function getTenantCount() { return count($this->tenants); }

    // Audit helper
    protected function audit($action, $userId = null, $meta = []) {
        if ($this->auditTrail) {
            $this->auditTrail->log($action, [], $userId, $meta);
        }
    }
}
