<?php
// Synchrenity string helpers

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
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
        $title = preg_replace('~[^\\/a-zA-Z0-9 _-]+~u', '', $title);
        $title = strtolower(trim($title));
        $title = preg_replace('~[\s_]+~', $separator, $title);
        return trim($title, $separator);
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
