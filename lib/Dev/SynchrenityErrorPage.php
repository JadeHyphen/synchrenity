<?php

declare(strict_types=1);

namespace Synchrenity\Dev;

// SynchrenityErrorPage: Beautiful, extensible error/debug page renderer
class SynchrenityErrorPage
{
    protected $theme   = 'auto'; // 'light', 'dark', 'auto'
    protected $plugins = [];
    protected $events  = [];
    protected $metrics = [ 'rendered' => 0, 'errors' => 0 ];
    protected $context = [];

    public function __construct($theme = 'auto')
    {
        $this->theme = $theme;
    }

    public function render($error, $options = [])
    {
        $this->metrics['rendered']++;
        $theme   = $options['theme'] ?? $this->theme;
        $id      = $error['id']      ?? null;
        $message = $error['message'] ?? 'Unknown error';
        $code    = $error['code']    ?? 500;
        $context = $error['context'] ?? [];
        $trace   = $error['trace']   ?? [];
        $file    = $context['file']  ?? null;
        $line    = $context['line']  ?? null;
        $html    = $this->renderHeader($theme);
        $html .= '<div class="synchrenity-error-main">';
        $html .= '<h1>Error ' . htmlspecialchars($code) . '</h1>';
        $html .= '<h2>' . htmlspecialchars($message) . '</h2>';

        if ($id) {
            $html .= '<div class="synchrenity-error-id">Error ID: ' . htmlspecialchars($id) . '</div>';
        }

        if (!empty($context)) {
            $html .= '<details open><summary>Context</summary><pre>' . htmlspecialchars(print_r($context, true)) . '</pre></details>';
        }

        if (!empty($trace)) {
            $html .= '<details open><summary>Stack Trace</summary>';
            $html .= $this->renderTrace($trace);
            $html .= '</details>';
        }

        if ($file && $line) {
            $html .= $this->renderCodePreview($file, $line);
        }
        $html .= $this->renderActions($error);
        $html .= '</div>';
        $html .= $this->renderFooter();
        $this->triggerEvent('render', $error);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onRender'])) {
                $plugin->onRender($error, $this);
            }
        }
        echo $html;
    }

    protected function renderHeader($theme)
    {
        $css = $this->getThemeCss($theme);

        return "<style>{$css}</style><div class='synchrenity-error-page'>";
    }

    protected function renderFooter()
    {
        return '</div>';
    }

    protected function renderTrace($trace)
    {
        $html = '<ol class="synchrenity-trace">';

        foreach ($trace as $frame) {
            $html .= '<li>';

            if (isset($frame['file'])) {
                $html .= htmlspecialchars($frame['file']) . ':' . htmlspecialchars($frame['line'] ?? '?') . ' - ';
            }
            $html .= htmlspecialchars($frame['function'] ?? '[function]');
            $html .= '</li>';
        }
        $html .= '</ol>';

        return $html;
    }

    protected function renderCodePreview($file, $line, $padding = 5)
    {
        if (!is_readable($file)) {
            return '';
        }
        $lines = @file($file);

        if (!$lines) {
            return '';
        }
        $start = max(0, $line - $padding - 1);
        $end   = min(count($lines), $line + $padding);
        $html  = '<details open><summary>Code Preview</summary><pre class="synchrenity-code">';

        for ($i = $start; $i < $end; $i++) {
            $highlight = ($i + 1 == $line) ? ' style="background: #ffe0e0;"' : '';
            $html .= '<span' . $highlight . '>' . str_pad((string)($i + 1), 4, ' ', STR_PAD_LEFT) . ': ' . htmlspecialchars(rtrim($lines[$i])) . "</span>\n";
        }
        $html .= '</pre></details>';

        return $html;
    }

    protected function renderActions($error)
    {
        $id       = $error['id'] ?? '';
        $copyBtn  = $id ? '<button onclick="navigator.clipboard.writeText(\'' . htmlspecialchars($id) . '\')">Copy Error ID</button>' : '';
        $shareBtn = $id ? '<button onclick="window.open(\'mailto:support@example.com?subject=Error%20ID%20' . htmlspecialchars($id) . '\')">Share</button>' : '';

        return '<div class="synchrenity-actions">' . $copyBtn . $shareBtn . '</div>';
    }

    protected function getThemeCss($theme)
    {
        $light = '.synchrenity-error-page{font-family:monospace;background:#fff;color:#222;padding:2em;} .synchrenity-error-main{max-width:800px;margin:auto;} .synchrenity-trace{color:#444;} .synchrenity-code{background:#f8f8f8;padding:1em;} .synchrenity-actions button{margin:0.5em;}';
        $dark  = '.synchrenity-error-page{font-family:monospace;background:#181818;color:#eee;padding:2em;} .synchrenity-error-main{max-width:800px;margin:auto;} .synchrenity-trace{color:#ccc;} .synchrenity-code{background:#222;padding:1em;} .synchrenity-actions button{margin:0.5em;}';

        if ($theme === 'dark') {
            return $dark;
        }

        if ($theme === 'light') {
            return $light;
        }

        return "@media (prefers-color-scheme: dark) { $dark } @media (prefers-color-scheme: light) { $light }";
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
    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
    }
    // Context
    public function setContext($key, $value)
    {
        $this->context[$key] = $value;
    }
    public function getContext($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }
    // Introspection
    public function getPlugins()
    {
        return $this->plugins;
    }
    public function getEvents()
    {
        return $this->events;
    }
}
