<?php

declare(strict_types=1);

namespace Synchrenity\I18n;

class SynchrenityI18n
{
    protected $locale         = 'en';
    protected $fallbackLocale = 'en';
    protected $strings        = [];
    protected $domains        = [];
    protected $context        = [];
    protected $hooks          = [];
    protected $plugins        = [];
    protected $events         = [];
    protected $cache          = [];
    protected $metrics        = [
        'lookups' => 0,
        'misses'  => 0,
        'hits'    => 0,
        'loaded'  => 0,
    ];

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }
    public function getLocale()
    {
        return $this->locale;
    }
    public function setFallbackLocale($locale)
    {
        $this->fallbackLocale = $locale;
    }
    public function getFallbackLocale()
    {
        return $this->fallbackLocale;
    }
    public function setContext($context)
    {
        $this->context = $context;
    }
    public function getContext()
    {
        return $this->context;
    }

    // Load strings (array, file, remote, domain)
    public function loadStrings($data, $locale = null, $domain = null)
    {
        $locale = $locale ?: $this->locale;
        $domain = is_string($domain) && strlen($domain) ? $domain : null;
        $arr    = [];

        if (is_string($data) && file_exists($data)) {
            $ext = pathinfo($data, PATHINFO_EXTENSION);

            if ($ext === 'json') {
                $arr = json_decode(file_get_contents($data), true);
            } elseif ($ext === 'php') {
                $arr = include $data;
            }
        } elseif (is_array($data)) {
            $arr = $data;
        } elseif (is_callable($data)) {
            $arr = call_user_func($data, $locale);
        }

        if ($domain !== null) {
            if (!isset($this->domains[$domain]) || !is_array($this->domains[$domain])) {
                $this->domains[$domain] = [];
            }
            $this->domains[$domain][$locale] = $arr;
        } else {
            $this->strings[$locale] = $arr;
        }
        $count = count($arr);
        $this->metrics['loaded'] += $count;
        $this->triggerEvent('load', ['locale' => $locale,'count' => $count]);
    }

    // Add custom translation hook (e.g., DB, API)
    public function addHook(callable $hook)
    {
        $this->hooks[] = $hook;
    }
    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
    }
    // Event system
    public function on($event, callable $cb)
    {
        $this->events[$event][] = $cb;
    }
    protected function triggerEvent($event, $data = null)
    {
        foreach ($this->events[$event] ?? [] as $cb) {
            call_user_func($cb, $data, $this);
        }
    }

    // Main translation method (ICU, plural, domain, context, plugins, events, cache, metrics)
    public function t($key, $replacements = [], $locale = null, $domain = null, $context = null)
    {
        $this->metrics['lookups']++;
        $locale   = $locale ?: $this->locale;
        $context  = $context ?: $this->context;
        $cacheKey = md5(json_encode([$key, $locale, $replacements, $domain, $context]));

        if (isset($this->cache[$cacheKey])) {
            $this->metrics['hits']++;

            return $this->cache[$cacheKey];
        }

        // Domain support
        if ($domain && isset($this->domains[$domain][$locale][$key])) {
            $value = $this->domains[$domain][$locale][$key];
        } else {
            $value = $this->strings[$locale][$key] ?? null;
        }

        // Plugins
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'translate'])) {
                $v = $plugin->translate($key, $locale, $replacements, $domain, $context);

                if ($v !== null) {
                    $value = $v;
                }
            }
        }

        // Hooks
        if ($value === null) {
            foreach ($this->hooks as $hook) {
                $v = call_user_func($hook, $key, $locale, $replacements, $domain, $context);

                if ($v !== null) {
                    $value = $v;
                    break;
                }
            }
        }

        // Fallback
        if ($value === null && $locale !== $this->fallbackLocale) {
            $value = $this->strings[$this->fallbackLocale][$key] ?? $key;
        }

        if ($value === null) {
            $this->metrics['misses']++;
            $value = $key;
        }

        // Interpolate parameters (advanced)
        if (!empty($replacements)) {
            $value = $this->interpolate($value, $replacements);
        }
        $this->cache[$cacheKey] = $value;
        $this->triggerEvent('translate', ['key' => $key,'locale' => $locale,'result' => $value]);

        return $value;
    }

    // Pluralization (ICU, array, fallback)
    public function plural($key, $count, $replacements = [], $locale = null, $domain = null, $context = null)
    {
        $locale  = $locale ?: $this->locale;
        $context = $context ?: $this->context;
        $forms   = null;

        if ($domain && isset($this->domains[$domain][$locale][$key])) {
            $forms = $this->domains[$domain][$locale][$key];
        } else {
            $forms = $this->strings[$locale][$key] ?? null;
        }

        if (is_array($forms)) {
            $str = $this->selectPlural($forms, $count, $locale);
        } else {
            $str = $key;
        }
        $replacements['count'] = $count;

        if (!empty($replacements)) {
            $str = $this->interpolate($str, $replacements);
        }

        return $str;
    }

    // Advanced plural selection (ICU)
    protected function selectPlural($forms, $count, $locale)
    {
        if (isset($forms[$count])) {
            return $forms[$count];
        }

        if (isset($forms['one']) && $count == 1) {
            return $forms['one'];
        }

        if (isset($forms['other'])) {
            return $forms['other'];
        }

        if (isset($forms[0]) && $count == 1) {
            return $forms[0];
        }

        if (isset($forms[1]) && $count != 1) {
            return $forms[1];
        }

        return is_array($forms) ? reset($forms) : $forms;
    }

    // Advanced interpolation (supports nested, ICU, etc)
    protected function interpolate($value, $params)
    {
        foreach ($params as $k => $v) {
            $value = preg_replace('/\{'.$k.'(:[^}]*)?\}/', $v, $value);
            $value = str_replace(':' . $k, $v, $value);
        }

        return $value;
    }

    // Locale negotiation
    public function negotiateLocale($available, $preferred)
    {
        foreach ((array)$preferred as $pref) {
            if (in_array($pref, $available)) {
                return $pref;
            }
        }

        return $this->fallbackLocale;
    }

    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
    }

    // Validation
    public function validate($locale)
    {
        return isset($this->strings[$locale]) && is_array($this->strings[$locale]);
    }

    // Advanced formatting
    public function formatDate($date, $format = null)
    {
        $format = $format ?: ($this->locale === 'en' ? 'Y-m-d' : 'd.m.Y');

        return date($format, strtotime($date));
    }
    public function formatNumber($number, $decimals = 0)
    {
        $locale = $this->locale;

        if ($locale === 'en') {
            return number_format($number, $decimals, '.', ',');
        }

        // Add more locale-specific formats as needed
        return number_format($number, $decimals, ',', '.');
    }
}
