<?php

declare(strict_types=1);

namespace Synchrenity\Plugins;

class SynchrenityPluginManager
{
    protected $plugins  = [];
    protected $metadata = [];
    protected $enabled  = [];
    protected $contexts = [];

    /**
     * Register a plugin with metadata and lifecycle hooks.
     * $plugin can be a callable or an array with keys: init, boot, shutdown, enable, disable, meta
     */
    public function register($name, $plugin)
    {
        if ($this->has($name)) {
            throw new \RuntimeException("Plugin '$name' is already registered.");
        }

        if (is_callable($plugin)) {
            $this->plugins[$name] = ['init' => $plugin];
        } elseif (is_array($plugin)) {
            $this->plugins[$name] = $plugin;
        } else {
            throw new \InvalidArgumentException('Plugin must be callable or array.');
        }
        $meta = $plugin['meta'] ?? [];

        // Dependency resolution
        if (!empty($meta['dependencies'])) {
            foreach ($meta['dependencies'] as $dep) {
                if (!$this->has($dep)) {
                    $this->enabled[$name] = false;
                    error_log("[PluginManager] Plugin '$name' disabled: missing dependency '$dep'.");

                    return;
                }
            }
        }

        // Version constraint check (simple >= only)
        if (!empty($meta['requires'])) {
            foreach ($meta['requires'] as $dep => $ver) {
                if ($this->has($dep)) {
                    $depMeta = $this->getMeta($dep);

                    if (!empty($depMeta['version']) && version_compare($depMeta['version'], $ver, '<')) {
                        $this->enabled[$name] = false;
                        error_log("[PluginManager] Plugin '$name' disabled: '$dep' version must be >= $ver.");

                        return;
                    }
                }
            }
        }
        $this->metadata[$name] = $meta;
        $this->enabled[$name]  = true;
        $this->contexts[$name] = [];

        // Call init if present
        if (isset($this->plugins[$name]['init'])) {
            try {
                call_user_func($this->plugins[$name]['init'], $this->contexts[$name]);
            } catch (\Throwable $e) {
                $this->enabled[$name] = false;
                error_log("[PluginManager] Failed to init plugin '$name': " . $e->getMessage());
            }
        }
    }

    /** Boot all enabled plugins (calls boot hook if present) */
    public function boot()
    {
        foreach ($this->plugins as $name => $plugin) {
            if (!$this->enabled[$name]) {
                continue;
            }

            if (isset($plugin['boot'])) {
                try {
                    call_user_func($plugin['boot'], $this->contexts[$name]);
                } catch (\Throwable $e) {
                    $this->enabled[$name] = false;
                    error_log("[PluginManager] Failed to boot plugin '$name': " . $e->getMessage());
                }
            }
        }
    }

    /** Shutdown all enabled plugins (calls shutdown hook if present) */
    public function shutdown()
    {
        foreach ($this->plugins as $name => $plugin) {
            if (!$this->enabled[$name]) {
                continue;
            }

            if (isset($plugin['shutdown'])) {
                try {
                    call_user_func($plugin['shutdown'], $this->contexts[$name]);
                } catch (\Throwable $e) {
                    error_log("[PluginManager] Failed to shutdown plugin '$name': " . $e->getMessage());
                }
            }
        }
    }

    /** Enable a plugin (calls enable hook if present) */
    public function enable($name)
    {
        if ($this->has($name) && !$this->enabled[$name]) {
            $this->enabled[$name] = true;

            if (isset($this->plugins[$name]['enable'])) {
                try {
                    call_user_func($this->plugins[$name]['enable'], $this->contexts[$name]);
                } catch (\Throwable $e) {
                    error_log("[PluginManager] Failed to enable plugin '$name': " . $e->getMessage());
                }
            }
        }
    }

    /** Disable a plugin (calls disable hook if present) */
    public function disable($name)
    {
        if ($this->has($name) && $this->enabled[$name]) {
            $this->enabled[$name] = false;

            if (isset($this->plugins[$name]['disable'])) {
                try {
                    call_user_func($this->plugins[$name]['disable'], $this->contexts[$name]);
                } catch (\Throwable $e) {
                    error_log("[PluginManager] Failed to disable plugin '$name': " . $e->getMessage());
                }
            }
        }
    }

    /** Check if a plugin is registered */
    public function has($name)
    {
        return isset($this->plugins[$name]);
    }

    /** Get a plugin's callable or array */
    public function get($name)
    {
        return $this->plugins[$name] ?? null;
    }

    /** Get plugin metadata */
    public function getMeta($name)
    {
        return $this->metadata[$name] ?? [];
    }

    /** Check if a plugin is enabled */
    public function isEnabled($name)
    {
        return $this->enabled[$name] ?? false;
    }

    /** Get plugin context (isolated per plugin) */
    public function getContext($name)
    {
        return $this->contexts[$name] ?? null;
    }

    /** Auto-discover and register plugins from a directory */
    public function discover($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*.php') as $file) {
            $plugin = include $file;

            if (is_array($plugin) && isset($plugin['name'])) {
                $this->register($plugin['name'], $plugin);
            }
        }
    }
}
