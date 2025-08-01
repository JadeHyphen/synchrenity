<?php

declare(strict_types=1);

namespace Synchrenity\Support;

/**
 * Configuration Manager for Synchrenity
 * Handles configuration loading, merging, and access with dot notation
 */
class SynchrenityConfigManager
{
    protected array $config = [];
    protected array $env = [];
    protected string $configPath;
    protected bool $cached = false;

    public function __construct(string $configPath = null)
    {
        $this->configPath = $configPath ?: __DIR__ . '/../../config';
    }

    /**
     * Load configuration from files
     */
    public function load(): void
    {
        if ($this->cached) {
            return;
        }

        // Load main configuration files
        $this->loadConfigFile('app');
        $this->loadConfigFile('services');
        
        // Load environment-specific config
        $env = $this->get('env', getenv('APP_ENV') ?: 'production');
        $this->loadConfigFile("app.{$env}");
        
        // Load additional configs
        $additionalConfigs = ['api_rate_limits', 'oauth2', 'security', 'plugins', 'i18n'];
        foreach ($additionalConfigs as $configName) {
            $this->loadConfigFile($configName);
        }

        $this->cached = true;
    }

    /**
     * Load a specific configuration file
     */
    protected function loadConfigFile(string $name): void
    {
        $filepath = $this->configPath . "/{$name}.php";
        
        if (!file_exists($filepath)) {
            return;
        }

        try {
            $config = require $filepath;
            
            if (is_array($config)) {
                $this->config = array_merge($this->config, $config);
            } elseif (is_object($config)) {
                // Handle config objects
                if (method_exists($config, 'all')) {
                    $this->config = array_merge($this->config, $config->all());
                }
                if (method_exists($config, 'envAll')) {
                    $this->env = array_merge($this->env, $config->envAll());
                }
            }
        } catch (\Throwable $e) {
            error_log("Failed to load config file {$name}: " . $e->getMessage());
        }
    }

    /**
     * Get configuration value using dot notation
     */
    public function get(string $key, $default = null)
    {
        $this->load();
        
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Set configuration value using dot notation
     */
    public function set(string $key, $value): void
    {
        $this->load();
        
        $segments = explode('.', $key);
        $config = &$this->config;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $config[$segment] = $value;
            } else {
                if (!isset($config[$segment]) || !is_array($config[$segment])) {
                    $config[$segment] = [];
                }
                $config = &$config[$segment];
            }
        }
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        $this->load();
        
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all configuration
     */
    public function all(): array
    {
        $this->load();
        return $this->config;
    }

    /**
     * Get environment variables
     */
    public function env(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->env;
        }

        return $this->env[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Reload configuration (useful for hot-reload in development)
     */
    public function reload(): void
    {
        $this->config = [];
        $this->env = [];
        $this->cached = false;
        $this->load();
    }

    /**
     * Merge additional configuration
     */
    public function merge(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get configuration for a specific environment
     */
    public function forEnvironment(string $environment): array
    {
        $envConfig = [];
        $envFile = $this->configPath . "/app.{$environment}.php";
        
        if (file_exists($envFile)) {
            $config = require $envFile;
            if (is_array($config)) {
                $envConfig = $config;
            }
        }
        
        return array_merge($this->all(), $envConfig);
    }

    /**
     * Check if we're in a specific environment
     */
    public function isEnvironment(string $environment): bool
    {
        $currentEnv = $this->get('env', $this->env('APP_ENV', 'production'));
        return $currentEnv === $environment;
    }

    /**
     * Check if debugging is enabled
     */
    public function debug(): bool
    {
        return $this->get('debug', false) || $this->isEnvironment('development') || $this->isEnvironment('dev');
    }

    /**
     * Export configuration to array (for service container)
     */
    public function toArray(): array
    {
        return $this->all();
    }
}