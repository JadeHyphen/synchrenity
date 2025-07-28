<?php
namespace Synchrenity\Plugins;


class SynchrenityPluginManager {
    protected $plugins = [];

    public function register($name, callable $init) {
        $this->plugins[$name] = $init;
    }

    public function boot() {
        foreach ($this->plugins as $name => $init) {
            call_user_func($init);
        }
    }

    public function has($name) {
        return isset($this->plugins[$name]);
    }

    public function get($name) {
        return $this->plugins[$name] ?? null;
    }
}
