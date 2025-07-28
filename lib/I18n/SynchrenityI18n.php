<?php
namespace Synchrenity\I18n;


class SynchrenityI18n {
    protected $locale = 'en';
    protected $strings = [];

    public function setLocale($locale) {
        $this->locale = $locale;
    }

    public function getLocale() {
        return $this->locale;
    }

    public function loadStrings(array $strings) {
        $this->strings = $strings;
    }

    public function t($key, $replacements = []) {
        $str = $this->strings[$this->locale][$key] ?? $key;
        foreach ($replacements as $k => $v) {
            $str = str_replace(':' . $k, $v, $str);
        }
        return $str;
    }

    public function plural($key, $count, $replacements = []) {
        $forms = $this->strings[$this->locale][$key] ?? [$key, $key];
        $str = $count == 1 ? $forms[0] : ($forms[1] ?? $forms[0]);
        $replacements['count'] = $count;
        foreach ($replacements as $k => $v) {
            $str = str_replace(':' . $k, $v, $str);
        }
        return $str;
    }

    public function formatDate($date, $format = null) {
        $format = $format ?: ($this->locale === 'en' ? 'Y-m-d' : 'd.m.Y');
        return date($format, strtotime($date));
    }

    public function formatNumber($number, $decimals = 0) {
        $locale = $this->locale;
        if ($locale === 'en') {
            return number_format($number, $decimals, '.', ',');
        }
        // Add more locale-specific formats as needed
        return number_format($number, $decimals, ',', '.');
    }
}
