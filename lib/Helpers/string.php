<?php
// Synchrenity string helpers: multibyte, unicode, plugin/event, metrics, context, inflection, normalization, extensibility

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return mb_substr($haystack, 0, mb_strlen($needle, 'UTF-8')) === $needle;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return mb_substr($haystack, -mb_strlen($needle, 'UTF-8'), null, 'UTF-8') === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle, 0, 'UTF-8') !== false;
    }
}

if (!function_exists('str_after')) {
    function str_after($subject, $search) {
        if ($search === '' || ($pos = strpos($subject, $search)) === false) {
            return $subject;
        }
        return substr($subject, $pos + strlen($search));
    }
}

if (!function_exists('str_before')) {
    function str_before($subject, $search) {
        if ($search === '' || ($pos = strpos($subject, $search)) === false) {
            return $subject;
        }
        return substr($subject, 0, $pos);
    }
}

if (!function_exists('str_between')) {
    function str_between($subject, $from, $to) {
        if ($from === '' || $to === '') return $subject;
        $start = strpos($subject, $from);
        if ($start === false) return '';
        $start += strlen($from);
        $end = strpos($subject, $to, $start);
        if ($end === false) return '';
        return substr($subject, $start, $end - $start);
    }
}

if (!function_exists('str_limit')) {
    function str_limit($value, $limit = 100, $end = '...') {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }
}

if (!function_exists('str_slug')) {
    function str_slug($title, $separator = '-') {
        // Unicode transliteration if possible
        if (class_exists('Transliterator')) {
            $title = \Transliterator::create('Any-Latin; Latin-ASCII; [\u0080-\uffff] remove')->transliterate($title);
        }
        $title = preg_replace('~[^a-zA-Z0-9 _-]+~u', '', $title);
        $title = mb_strtolower(trim($title), 'UTF-8');
        $title = preg_replace('~[\s_]+~u', $separator, $title);
        return trim($title, $separator);
    }
}
// Plugin/event/metrics/context/introspection system for string helpers
if (!function_exists('str_helper_register_plugin')) {
    function str_helper_register_plugin($plugin) {
        static $plugins = [];
        $plugins[] = $plugin;
    }
}
if (!function_exists('str_helper_on')) {
    function str_helper_on($event, callable $cb) {
        static $events = [];
        $events[$event][] = $cb;
    }
}
if (!function_exists('str_helper_trigger')) {
    function str_helper_trigger($event, $data = null) {
        static $events = [];
        foreach ($events[$event] ?? [] as $cb) call_user_func($cb, $data);
    }
}
if (!function_exists('str_helper_metrics')) {
    function str_helper_metrics() {
        static $metrics = [
            'calls' => 0,
            'errors' => 0
        ];
        return $metrics;
    }
}
if (!function_exists('str_helper_set_context')) {
    function str_helper_set_context($key, $value) {
        static $context = [];
        $context[$key] = $value;
    }
}
if (!function_exists('str_helper_get_context')) {
    function str_helper_get_context($key, $default = null) {
        static $context = [];
        return $context[$key] ?? $default;
    }
}
if (!function_exists('str_helper_plugins')) {
    function str_helper_plugins() {
        static $plugins = [];
        return $plugins;
    }
}
if (!function_exists('str_helper_events')) {
    function str_helper_events() {
        static $events = [];
        return $events;
    }
}
// Unicode normalization
if (!function_exists('str_normalize')) {
    function str_normalize($string, $form = 'NFC') {
        if (class_exists('Normalizer')) {
            return \Normalizer::normalize($string, constant('Normalizer::' . $form));
        }
        return $string;
    }
}

// Pluralization/singularization (simple English)
if (!function_exists('str_plural')) {
    function str_plural($word) {
        if (preg_match('/(s|x|z|ch|sh)$/i', $word)) return $word . 'es';
        if (preg_match('/y$/i', $word)) return preg_replace('/y$/i', 'ies', $word);
        return $word . 's';
    }
}
if (!function_exists('str_singular')) {
    function str_singular($word) {
        if (preg_match('/ies$/i', $word)) return preg_replace('/ies$/i', 'y', $word);
        if (preg_match('/(s|x|z|ch|sh)es$/i', $word)) return preg_replace('/es$/i', '', $word);
        if (preg_match('/s$/i', $word)) return preg_replace('/s$/i', '', $word);
        return $word;
    }
}

// Case conversion
if (!function_exists('str_camel')) {
    function str_camel($value) {
        $value = preg_replace('/[^a-zA-Z0-9]+/', ' ', $value);
        $value = ucwords(strtolower($value));
        $value = str_replace(' ', '', $value);
        $value[0] = strtolower($value[0]);
        return $value;
    }
}
if (!function_exists('str_snake')) {
    function str_snake($value, $delimiter = '_') {
        $value = preg_replace('/[A-Z]/', $delimiter . '$0', $value);
        return strtolower(ltrim($value, $delimiter));
    }
}
if (!function_exists('str_studly')) {
    function str_studly($value) {
        $value = preg_replace('/[^a-zA-Z0-9]+/', ' ', $value);
        $value = ucwords(strtolower($value));
        return str_replace(' ', '', $value);
    }
}

// Advanced search/replace
if (!function_exists('str_replace_first')) {
    function str_replace_first($search, $replace, $subject) {
        $pos = mb_strpos($subject, $search, 0, 'UTF-8');
        if ($pos === false) return $subject;
        return mb_substr($subject, 0, $pos, 'UTF-8') . $replace . mb_substr($subject, $pos + mb_strlen($search, 'UTF-8'), null, 'UTF-8');
    }
}
if (!function_exists('str_replace_last')) {
    function str_replace_last($search, $replace, $subject) {
        $pos = mb_strrpos($subject, $search, 0, 'UTF-8');
        if ($pos === false) return $subject;
        return mb_substr($subject, 0, $pos, 'UTF-8') . $replace . mb_substr($subject, $pos + mb_strlen($search, 'UTF-8'), null, 'UTF-8');
    }
}

if (!function_exists('str_is')) {
    function str_is($pattern, $value) {
        if ($pattern === $value) return true;
        $pattern = str_replace('\\*', '.*', preg_quote($pattern, '#'));
        return (bool) preg_match('#^' . $pattern . '\z#u', $value);
    }
}

if (!function_exists('str_repeat')) {
    function str_repeat($input, $multiplier) {
        return str_repeat($input, $multiplier);
    }
}

if (!function_exists('str_pad')) {
    function str_pad_custom($input, $length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT) {
        return str_pad($input, $length, $pad_string, $pad_type);
    }
}

if (!function_exists('str_random')) {
    function str_random($length = 16) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
