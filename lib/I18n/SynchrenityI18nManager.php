<?php

declare(strict_types=1);

namespace Synchrenity\I18n;

class SynchrenityI18nManager
{
    protected $auditTrail;
    protected $translations   = [];
    protected $locale         = 'en';
    protected $fallbackLocale = 'en';
    protected $hooks          = [];
    protected $domains        = [];
    protected $context        = [];
    protected $cache          = [];
    protected $plugins        = [];
    protected $events         = [];
    protected $metrics        = [
        'lookups' => 0,
        'misses'  => 0,
        'hits'    => 0,
        'loaded'  => 0,
    ];

    public function setAuditTrail($auditTrail)
    {
        $this->auditTrail = $auditTrail;
    }
    // Domains (e.g. 'messages', 'errors')
    public function setDomain($domain, $translations)
    {
        $this->domains[$domain] = $translations;
    }
    public function getDomain($domain)
    {
        return $this->domains[$domain] ?? [];
    }
    // Context (e.g. gender, region)
    public function setContext($context)
    {
        $this->context = $context;
    }
    public function getContext()
    {
        return $this->context;
    }

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

    // Load translations from array or file (JSON, YAML, PHP, remote)
    public function loadTranslations($locale, $data, $domain = null)
    {
        $domain = is_string($domain) && strlen($domain) ? $domain : null;
        $arr    = [];

        if (is_string($data) && file_exists($data)) {
            $ext = pathinfo($data, PATHINFO_EXTENSION);

            if ($ext === 'json') {
                $json = file_get_contents($data);
                $arr  = json_decode($json, true);
            } elseif ($ext === 'php') {
                $arr = include $data;
            }
        } elseif (is_array($data)) {
            $arr = $data;
        } elseif (is_callable($data)) {
            // Async/remote loader
            $arr = call_user_func($data, $locale);
        }

        if ($domain !== null) {
            if (!isset($this->domains[$domain]) || !is_array($this->domains[$domain])) {
                $this->domains[$domain] = [];
            }
            $this->domains[$domain][$locale] = $arr;
            $count                           = count($this->domains[$domain][$locale] ?? []);
        } else {
            $this->translations[$locale] = $arr;
            $count                       = count($this->translations[$locale] ?? []);
        }
        $this->metrics['loaded'] += $count;

        if ($this->auditTrail) {
            $this->auditTrail->log('load_translations', ['locale' => $locale, 'count' => $count], null);
        }
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
    public function translate($key, $locale = null, $params = [], $count = null, $domain = null, $context = null)
    {
        $this->metrics['lookups']++;
        $locale   = $locale  ?? $this->locale;
        $context  = $context ?? $this->context;
        $cacheKey = md5(json_encode([$key, $locale, $params, $count, $domain, $context]));

        if (isset($this->cache[$cacheKey])) {
            $this->metrics['hits']++;

            return $this->cache[$cacheKey];
        }

        // Domain support
        if ($domain && isset($this->domains[$domain][$locale][$key])) {
            $value = $this->domains[$domain][$locale][$key];
        } else {
            $value = $this->getTranslation($key, $locale, $count, $context);
        }

        // Plugins
        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'translate'])) {
                $v = $plugin->translate($key, $locale, $params, $count, $domain, $context);

                if ($v !== null) {
                    $value = $v;
                }
            }
        }

        // Hooks
        if ($value === null) {
            foreach ($this->hooks as $hook) {
                $v = call_user_func($hook, $key, $locale, $params, $count, $domain, $context);

                if ($v !== null) {
                    $value = $v;
                    break;
                }
            }
        }

        // Fallback
        if ($value === null && $locale !== $this->fallbackLocale) {
            $value = $this->getTranslation($key, $this->fallbackLocale, $count, $context);
        }

        if ($value === null) {
            $this->metrics['misses']++;
            $value = $key; // Return key if missing
        }

        // ICU/pluralization
        if (is_array($value) && $count !== null) {
            $value = $this->selectPlural($value, $count, $locale);
        }

        // Interpolate parameters (advanced)
        if (!empty($params)) {
            $value = $this->interpolate($value, $params);
        }
        $this->cache[$cacheKey] = $value;

        if ($this->auditTrail) {
            $this->auditTrail->log('translate', ['key' => $key, 'locale' => $locale, 'result' => $value], null);
        }
        $this->triggerEvent('translate', ['key' => $key,'locale' => $locale,'result' => $value]);

        return $value;
    }

    // Internal: get translation, handle pluralization, context
    protected function getTranslation($key, $locale, $count = null, $context = null)
    {
        $trans = $this->translations[$locale][$key] ?? null;

        // Contextual translation
        if (is_array($trans) && $context && isset($trans['@context'])) {
            foreach ($trans['@context'] as $ctx => $val) {
                if ($ctx == $context) {
                    return $val;
                }
            }
        }

        // Pluralization: ICU style or [0]=singular, [1]=plural, etc.
        if (is_array($trans) && $count !== null) {
            return $this->selectPlural($trans, $count, $locale);
        }

        return $trans;
    }

    // Advanced plural selection (ICU)
    protected function selectPlural($forms, $count, $locale)
    {
        // Simple: [0]=singular, [1]=plural, or ICU keys
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
        // ICU: {name}, {count}, etc.
        foreach ($params as $k => $v) {
            $value = preg_replace('/\{'.$k.'(:[^}]*)?\}/', $v, $value);
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
        return isset($this->translations[$locale]) && is_array($this->translations[$locale]);
    }
}
