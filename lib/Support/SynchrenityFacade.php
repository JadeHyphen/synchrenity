<?php
namespace Synchrenity\Support;

class SynchrenityFacade {
    protected static $container;

    public static function setContainer($container) {
        static::$container = $container;
    }

    public static function __callStatic($method, $args) {
        $service = static::$container->get(static::getServiceName());
        return call_user_func_array([$service, $method], $args);
    }

    protected static function getServiceName() {
        throw new \Exception('Facade must implement getServiceName()');
    }
}
