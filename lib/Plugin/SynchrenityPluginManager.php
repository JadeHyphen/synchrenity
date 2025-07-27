<?php
namespace Synchrenity\Plugin;

class SynchrenityPluginManager {
    protected $auditTrail;
    protected $plugins = [];
    protected $active = [];
    protected $hooks = [];

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }

    // Register a plugin (metadata: name, version, dependencies, path)
    public function register($plugin, $meta = []) {
        $this->plugins[$plugin] = $meta;
        $this->audit('register_plugin', null, ['plugin' => $plugin, 'meta' => $meta]);
    }

    // Discover all registered plugins
    public function listPlugins() {
        return array_keys($this->plugins);
    }

    // Load and activate plugin
    public function load($plugin) {
        if (!isset($this->plugins[$plugin])) {
            $this->audit('load_failed', null, ['plugin' => $plugin, 'reason' => 'not_registered']);
            return false;
        }
        $meta = $this->plugins[$plugin];
        // Version/dependency checks (stub)
        if (isset($meta['dependencies'])) {
            foreach ($meta['dependencies'] as $dep) {
                if (!isset($this->plugins[$dep])) {
                    $this->audit('load_failed', null, ['plugin' => $plugin, 'reason' => 'missing_dependency', 'dependency' => $dep]);
                    return false;
                }
            }
        }
        // Actual load logic (stub: require file)
        if (isset($meta['path']) && file_exists($meta['path'])) {
            require_once $meta['path'];
        }
        $this->active[$plugin] = true;
        foreach ($this->hooks as $hook) {
            call_user_func($hook, $plugin, $meta);
        }
        $this->audit('load_plugin', null, ['plugin' => $plugin, 'meta' => $meta]);
        return true;
    }

    // Deactivate plugin
    public function unload($plugin) {
        if (isset($this->active[$plugin])) {
            unset($this->active[$plugin]);
            $this->audit('unload_plugin', null, ['plugin' => $plugin]);
            return true;
        }
        return false;
    }

    // Check if plugin is active
    public function isActive($plugin) {
        return !empty($this->active[$plugin]);
    }

    // Add custom hook (e.g., for plugin events)
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
