<?php
namespace Synchrenity\Http;

/**
 * SynchrenityEventManager: Register and dispatch HTTP lifecycle events
 */
class SynchrenityEventManager
{
    protected $listeners = [];

    public function on($event, $callback)
    {
        $this->listeners[$event][] = $callback;
    }

    public function dispatch($event, ...$args)
    {
        if (!empty($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $cb) {
                call_user_func_array($cb, $args);
            }
        }
    }
}
