<?php
namespace Synchrenity\I18n;

class SynchrenityI18nManager {
    protected $auditTrail;
    protected $translations = [];
    protected $locale = 'en';
    protected $fallbackLocale = 'en';
    protected $hooks = [];

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }

    public function setLocale($locale) {
        $this->locale = $locale;
    }
    public function setFallbackLocale($locale) {
        $this->fallbackLocale = $locale;
    }

    // Load translations from array or file (JSON)
    public function loadTranslations($locale, $data) {
        if (is_string($data) && file_exists($data)) {
            $json = file_get_contents($data);
            $this->translations[$locale] = json_decode($json, true);
        } elseif (is_array($data)) {
            $this->translations[$locale] = $data;
        }
        if ($this->auditTrail) {
            $this->auditTrail->log('load_translations', ['locale' => $locale, 'count' => count($this->translations[$locale] ?? [])], null);
        }
    }

    // Add custom translation hook (e.g., DB, API)
    public function addHook(callable $hook) {
        $this->hooks[] = $hook;
    }

    // Main translation method
    public function translate($key, $locale = null, $params = [], $count = null) {
        $locale = $locale ?? $this->locale;
        $value = $this->getTranslation($key, $locale, $count);
        if ($value === null) {
            // Try hooks for custom sources
            foreach ($this->hooks as $hook) {
                $value = call_user_func($hook, $key, $locale, $params, $count);
                if ($value !== null) break;
            }
        }
        if ($value === null && $locale !== $this->fallbackLocale) {
            $value = $this->getTranslation($key, $this->fallbackLocale, $count);
        }
        if ($value === null) {
            $value = $key; // Return key if missing
        }
        // Interpolate parameters
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $value = str_replace('{' . $k . '}', $v, $value);
            }
        }
        if ($this->auditTrail) {
            $this->auditTrail->log('translate', ['key' => $key, 'locale' => $locale, 'result' => $value], null);
        }
        return $value;
    }

    // Internal: get translation, handle pluralization
    protected function getTranslation($key, $locale, $count = null) {
        $trans = $this->translations[$locale][$key] ?? null;
        if (is_array($trans) && $count !== null) {
            // Pluralization: [0] = singular, [1] = plural
            return $count == 1 ? ($trans[0] ?? $key) : ($trans[1] ?? $key);
        }
        return $trans;
    }
}
