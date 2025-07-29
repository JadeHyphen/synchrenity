<?php

declare(strict_types=1);

// Synchrenity array helpers: atomic, plugin/event, metrics, context, deep, dot, immutable, extensible
// Plugin/event/metrics/context/introspection system for array helpers
if (!function_exists('array_helper_register_plugin')) {
    function array_helper_register_plugin($plugin)
    {
        static $plugins = [];
        $plugins[]      = $plugin;
    }
}

if (!function_exists('array_helper_on')) {
    function array_helper_on($event, callable $cb)
    {
        static $events    = [];
        $events[$event][] = $cb;
    }
}

if (!function_exists('array_helper_trigger')) {
    function array_helper_trigger($event, $data = null)
    {
        static $events = [];

        foreach ($events[$event] ?? [] as $cb) {
            call_user_func($cb, $data);
        }
    }
}

if (!function_exists('array_helper_metrics')) {
    function array_helper_metrics()
    {
        static $metrics = [
            'calls'  => 0,
            'errors' => 0,
        ];

        return $metrics;
    }
}

if (!function_exists('array_helper_set_context')) {
    function array_helper_set_context($key, $value)
    {
        static $context = [];
        $context[$key]  = $value;
    }
}

if (!function_exists('array_helper_get_context')) {
    function array_helper_get_context($key, $default = null)
    {
        static $context = [];

        return $context[$key] ?? $default;
    }
}

if (!function_exists('array_helper_plugins')) {
    function array_helper_plugins()
    {
        static $plugins = [];

        return $plugins;
    }
}

if (!function_exists('array_helper_events')) {
    function array_helper_events()
    {
        static $events = [];

        return $events;
    }
}

// Deep merge (recursive, immutable)
if (!function_exists('array_deep_merge')) {
    function array_deep_merge(...$arrays)
    {
        $base = array_shift($arrays);

        foreach ($arrays as $append) {
            foreach ($append as $key => $value) {
                if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                    $base[$key] = array_deep_merge($base[$key], $value);
                } else {
                    $base[$key] = $value;
                }
            }
        }

        return $base;
    }
}

// Flatten with depth
if (!function_exists('array_flatten_depth')) {
    function array_flatten_depth($array, $depth = INF)
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item) && $depth > 1) {
                $result = array_merge($result, array_flatten_depth($item, $depth - 1));
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }
}

// Dot/undot helpers
if (!function_exists('array_dot')) {
    function array_dot($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results += array_dot($value, $prepend . $key . '.');
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }
}

if (!function_exists('array_undot')) {
    function array_undot($array)
    {
        $result = [];

        foreach ($array as $key => $value) {
            array_set($result, $key, $value);
        }

        return $result;
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, $key, $default = null)
    {
        if (!is_array($array)) {
            return $default;
        }

        if (is_null($key)) {
            return $array;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }
}

if (!function_exists('array_has')) {
    function array_has(array $array, $key)
    {
        if (!is_array($array)) {
            return false;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('array_set')) {
    function array_set(array &$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $segment = array_shift($keys);

            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }
        $array[array_shift($keys)] = $value;

        return $array;
    }
}

if (!function_exists('array_forget')) {
    function array_forget(array &$array, $key)
    {
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $segment = array_shift($keys);

            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                return;
            }
            $array = &$array[$segment];
        }
        unset($array[array_shift($keys)]);
    }
}

if (!function_exists('array_pluck')) {
    function array_pluck(array $array, $value, $key = null)
    {
        $results = [];

        foreach ($array as $item) {
            $itemValue = is_array($item) ? array_get($item, $value) : null;

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $itemKey           = is_array($item) ? array_get($item, $key) : null;
                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }
}

if (!function_exists('array_first')) {
    function array_first(array $array, ?callable $callback = null, $default = null)
    {
        foreach ($array as $key => $value) {
            if ($callback === null || $callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }
}

if (!function_exists('array_last')) {
    function array_last(array $array, ?callable $callback = null, $default = null)
    {
        $reversed = array_reverse($array, true);

        foreach ($reversed as $key => $value) {
            if ($callback === null || $callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }
}

if (!function_exists('array_flatten')) {
    function array_flatten($array)
    {
        return array_flatten_depth($array, INF);
    }
}

if (!function_exists('array_only')) {
    function array_only(array $array, $keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        if (!is_array($keys)) {
            $keys = [$keys];
        }

        return array_intersect_key($array, array_flip($keys));
    }
}

if (!function_exists('array_except')) {
    function array_except(array $array, $keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        if (!is_array($keys)) {
            $keys = [$keys];
        }

        return array_diff_key($array, array_flip($keys));
    }
}

if (!function_exists('array_is_assoc')) {
    function array_is_assoc(array $array)
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}

if (!function_exists('array_wrap')) {
    function array_wrap($value)
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}

if (!function_exists('array_filter_recursive')) {
    function array_filter_recursive($array, $callback = null)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = array_filter_recursive($value, $callback);
            }
        }

        return is_null($callback) ? array_filter($array) : array_filter($array, $callback);
    }
}

if (!function_exists('array_key_exists_any')) {
    function array_key_exists_any($keys, array $array)
    {
        foreach ((array)$keys as $key) {
            if (array_key_exists($key, $array)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('array_key_exists_all')) {
    function array_key_exists_all($keys, array $array)
    {
        foreach ((array)$keys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('array_value_exists')) {
    function array_value_exists($value, $array)
    {
        foreach ($array as $item) {
            if (is_array($item)) {
                if (array_value_exists($value, $item)) {
                    return true;
                }
            } elseif ($item === $value) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('array_partition')) {
    function array_partition(array $array, callable $callback)
    {
        $truthy = $falsy = [];

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                $truthy[$key] = $value;
            } else {
                $falsy[$key] = $value;
            }
        }

        return [$truthy, $falsy];
    }
}
