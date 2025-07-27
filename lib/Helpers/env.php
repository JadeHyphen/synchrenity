<?php
// Synchrenity Environment Loader
// Provides synchrenity_env() and env() helpers for robust .env support
if (!function_exists('synchrenity_env')) {
    /**
     * Load and parse .env file into an array
// ...existing code...
     * Supports variable expansion, type casting, multi-line values
     */
    function synchrenity_env($file)
    {
        $env = [];
        if (!file_exists($file)) return $env;
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $multilineKey = null;
        $multilineValue = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if ($multilineKey) {
                $multilineValue .= "\n" . $line;
                if (substr($line, -1) === '"') {
                    $val = trim($multilineValue, '"');
                    $val = preg_replace_callback('/\${([A-Z0-9_]+)}/', function($m) use ($env) {
                        return $env[$m[1]] ?? '';
                    }, $val);
                    $env[$multilineKey] = $val;
                    $multilineKey = null;
                    $multilineValue = '';
                }
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                if ($val && $val[0] === '"' && substr($val, -1) !== '"') {
                    $multilineKey = $key;
                    $multilineValue = ltrim($val, '"');
                    continue;
                }
                if ($val && $val[0] === '"') {
                    $val = trim($val, '"');
                }
                $val = preg_replace_callback('/\${([A-Z0-9_]+)}/', function($m) use ($env) {
                    return $env[$m[1]] ?? '';
                }, $val);
                if (strtolower($val) === 'true') $val = true;
                elseif (strtolower($val) === 'false') $val = false;
                elseif (strtolower($val) === 'null') $val = null;
                elseif (is_numeric($val)) $val = $val + 0;
                $env[$key] = $val;
            }
        }
        return $env;
    }
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable or returns a default value.
     * Supports type casting for booleans, null, int, float.
     */
    function env($key, $default = null)
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true' || $lower === '(true)') return true;
            if ($lower === 'false' || $lower === '(false)') return false;
            if ($lower === 'null' || $lower === '(null)') return null;
            if (is_numeric($value)) {
                if (strpos($value, '.') !== false) return (float)$value;
                return (int)$value;
            }
        }
        return $value;
    }
}
            

        
