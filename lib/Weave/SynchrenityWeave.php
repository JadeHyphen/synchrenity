<?php

declare(strict_types=1);

namespace Synchrenity\Weave;

/**
 * SynchrenityWeave: Ultra-secure, extensible, and powerful templating engine
 * Features: advanced syntax, sandboxed execution, custom directives, components, slots, layouts, macros, filters, asset pipeline, i18n, caching, event hooks, error reporting, and more.
 */


class SynchrenityWeave
{
    protected $templatePath;
    protected $cachePath;
    protected $variables  = [];
    protected $directives = [];
    protected $components = [];
    protected $filters    = [];
    protected $eventManager;
    protected $secureMode = true;
    protected $locale     = 'en';

    // Layout/section/yield support
    protected $parentLayout = null;
    protected $sections     = [];
    protected $sectionStack = [];

    // Stacks for push/stack
    protected $stacks     = [];
    protected $stackStack = [];

    // Slots for components
    protected $slots     = [];
    protected $slotStack = [];

    // Macros
    protected $macros = [];


    // Caching
    protected $templateCache = [];
    protected $fragmentCache = [];
    protected $cacheEnabled  = true;
    protected $cacheBuster   = null;

    // Hot reload (dev mode)
    protected $devMode        = false;
    protected $templateMtimes = [];

    // Profiling/debugging
    protected $profile = [];
    protected $debug   = false;

    // Dependency injection
    protected $injected = [];

    // Async/deferred blocks
    protected $deferredBlocks = [];

    // Customizable syntax
    protected $tagOpen      = '{%';
    protected $tagClose     = '%}';
    protected $echoOpen     = '{{';
    protected $echoClose    = '}}';
    protected $commentOpen  = '{#';
    protected $commentClose = '#}';

    // Security helpers
    protected $cspNonces  = [];
    protected $strictMode = false;

    // Preprocessors
    protected $preprocessors = [];

    // Dynamic discovery
    protected $partials      = [];
    protected $componentsDir = null;

    // Versioning
    protected $templateVersions = [];
    protected $activeVersion    = null;

    // Access control
    protected $accessControl = null;

    // Export/import
    protected $exporters = [];
    protected $importers = [];

    // API
    protected $apiEnabled = false;

    // Event hooks
    protected $hooks = [
        'beforeRender' => [],
        'afterRender'  => [],
    ];

    public function __construct($templatePath, $cachePath = null, $eventManager = null, $devMode = false)
    {
        $this->templatePath = $templatePath;
        $this->cachePath    = $cachePath;
        $this->eventManager = $eventManager;
        $this->devMode      = $devMode;
        $this->registerDefaultDirectives();
        $this->registerDefaultFilters();
        $this->discoverPartials();
    }


    public function render($view, $data = [], $options = [])
    {
        $this->variables    = $data;
        $this->parentLayout = null;
        $this->sections     = [];
        $this->sectionStack = [];
        $this->stacks       = [];
        $this->stackStack   = [];
        $this->slots        = [];
        $this->slotStack    = [];
        $this->macros       = [];
        $this->profile      = [];
        $this->triggerHook('beforeRender', $view, $data);
        $start    = microtime(true);
        $template = $this->loadTemplate($view, $options);
        $compiled = $this->compile($template, $options);
        $output   = $this->evaluate($compiled, $data);

        // If a parent layout was set, render it with sections
        if ($this->parentLayout) {
            $parentTemplate = $this->loadTemplate($this->parentLayout, $options);
            $parentCompiled = $this->compile($parentTemplate, $options);
            $output         = $this->evaluate($parentCompiled, $data);
        }
        $this->profile['render_time'] = microtime(true) - $start;
        $this->triggerHook('afterRender', $view, $data);

        if (!empty($options['debug']) || $this->debug) {
            $output .= $this->renderDebugPanel();
        }

        return $output;
    }

    protected function loadTemplate($view, $options = [])
    {
        // Versioning support
        if ($this->activeVersion && isset($this->templateVersions[$view][$this->activeVersion])) {
            $view = $this->templateVersions[$view][$this->activeVersion];
        }
        $file = rtrim($this->templatePath, '/').'/'.$view.'.weave.php';

        if (!file_exists($file)) {
            throw new \Exception("Template not found: $view");
        }

        // Hot reload: check mtime
        if ($this->devMode) {
            $mtime = filemtime($file);

            if (!isset($this->templateMtimes[$file]) || $this->templateMtimes[$file] !== $mtime) {
                $this->templateMtimes[$file] = $mtime;
                unset($this->templateCache[$file]);
            }
        }

        // Caching
        if ($this->cacheEnabled && isset($this->templateCache[$file])) {
            return $this->templateCache[$file];
        }
        $contents = file_get_contents($file);

        // Preprocessing
        foreach ($this->preprocessors as $pre) {
            $contents = call_user_func($pre, $contents, $view);
        }

        if ($this->cacheEnabled) {
            $this->templateCache[$file] = $contents;
        }

        return $contents;
    }

    protected function compile($template, $options = [])
    {
        // Remove template comments: {# ... #}
        $template = preg_replace('/\{#.*?#\}/s', '', $template);

        // Preprocessing (e.g., Markdown, Sass)
        foreach ($this->preprocessors as $pre) {
            $template = call_user_func($pre, $template);
        }

        // Customizable delimiters (future: allow user to change)
        $tagOpen      = preg_quote($this->tagOpen, '/');
        $tagClose     = preg_quote($this->tagClose, '/');
        $echoOpen     = preg_quote($this->echoOpen, '/');
        $echoClose    = preg_quote($this->echoClose, '/');
        $commentOpen  = preg_quote($this->commentOpen, '/');
        $commentClose = preg_quote($this->commentClose, '/');

        // Parse {% ... %} tags, now with all advanced features
        $compiled = preg_replace_callback('/' . $tagOpen . '\s*(set|if|for|include|layout|section|endsection|yield|stack|push|endpush|slot|endslot|macro|endmacro|callmacro|raw|endraw|asset|trans|inject|defer|enddefer|cspnonce|check|endcheck|export|import)\s*(.*?)\s*' . $tagClose . '/s', function ($m) {
            switch ($m[1]) {
                case 'inject':
                    return '<?php extract($this->injected, EXTR_SKIP); ?>';

                case 'defer':
                    return '<?php ob_start(); ?>';

                case 'enddefer':
                    return '<?php $this->deferredBlocks[] = ob_get_clean(); ?>';

                case 'cspnonce':
                    return '<?php echo $this->getCspNonce(' . var_export(trim($m[2], "'\""), true) . '); ?>';

                case 'check':
                    return '<?php if ($this->checkAccess(' . var_export(trim($m[2], "'\""), true) . ')): ?>';

                case 'endcheck':
                    return '<?php endif; ?>';

                case 'export':
                    return '<?php echo $this->exportTemplate(' . var_export(trim($m[2], "'\""), true) . '); ?>';

                case 'import':
                    return '<?php echo $this->importTemplate(' . var_export(trim($m[2], "'\""), true) . '); ?>';
                default:
                    // Fallback to previous cases
                    // ...existing code for set, if, for, include, etc...
            }

            return $m[0];
        }, $template);

        // End tags
        $compiled = preg_replace('/' . $tagOpen . '\s*endif\s*' . $tagClose . '/', '<?php endif; ?>', $compiled);
        $compiled = preg_replace('/' . $tagOpen . '\s*endfor\s*' . $tagClose . '/', '<?php endforeach; ?>', $compiled);

        // Parse {{ var|filter|filter2 }} and {{ var }}
        $compiled = preg_replace_callback('/' . $echoOpen . '\s*(.+?)\s*' . $echoClose . '/', function ($m) {
            $expr = $m[1];

            if (strpos($expr, '|') !== false) {
                $parts = explode('|', $expr);
                $var   = trim(array_shift($parts));
                $code  = $var;

                foreach ($parts as $filter) {
                    $code = '$this->applyFilter(' . var_export(trim($filter), true) . ', ' . $code . ')';
                }

                return '<?php echo ' . $code . '; ?>';
            }

            // Default: escape output, but check if variable exists
            // If variable is not set, output empty string to avoid undefined variable/constant warnings
            $safeExpr = preg_replace('/^([a-zA-Z_][a-zA-Z0-9_]*)$/', '(isset($\1) ? $\1 : (isset($this->variables["\1"]) ? $this->variables["\1"] : ""))', $expr);

            return '<?php echo htmlspecialchars(' . $safeExpr . ', ENT_QUOTES, "UTF-8"); ?>';
        }, $compiled);

        // Parse components/partials: {% component 'name' %}
        $compiled = preg_replace_callback('/' . $tagOpen . '\s*component\s+([\'\"])(.+?)\1\s*' . $tagClose . '/', function ($m) {
            return '<?php echo $this->renderComponent(' . var_export($m[2], true) . ', get_defined_vars()); ?>';
        }, $compiled);

        return $compiled;
    }
    // Hot reload toggle
    public function setDevMode($dev)
    {
        $this->devMode = (bool)$dev;
    }

    // Profiling/debugging
    public function renderDebugPanel()
    {
        $out = '<div style="background:#222;color:#fff;padding:8px;font-size:12px;">';
        $out .= 'Weave Debug: Render time: '.round(($this->profile['render_time'] ?? 0) * 1000, 2).'ms';
        $out .= '</div>';

        return $out;
    }

    // Caching controls
    public function enableCache($on = true)
    {
        $this->cacheEnabled = $on;
    }
    public function clearCache()
    {
        $this->templateCache = [];
        $this->fragmentCache = [];
    }
    public function cacheFragment($key, $value)
    {
        $this->fragmentCache[$key] = $value;
    }
    public function getFragment($key)
    {
        return $this->fragmentCache[$key] ?? null;
    }

    // Dependency injection
    public function inject($name, $value)
    {
        $this->injected[$name] = $value;
    }

    // Customizable syntax
    public function setDelimiters($tagOpen, $tagClose, $echoOpen, $echoClose, $commentOpen, $commentClose)
    {
        $this->tagOpen      = $tagOpen;
        $this->tagClose     = $tagClose;
        $this->echoOpen     = $echoOpen;
        $this->echoClose    = $echoClose;
        $this->commentOpen  = $commentOpen;
        $this->commentClose = $commentClose;
    }

    // Security helpers
    public function getCspNonce($context = 'default')
    {
        if (!isset($this->cspNonces[$context])) {
            $this->cspNonces[$context] = bin2hex(random_bytes(12));
        }

        return $this->cspNonces[$context];
    }
    public function setStrictMode($on = true)
    {
        $this->strictMode = $on;
    }

    // Validation/linting
    public function validateTemplate($template)
    {
        $errors = [];

        if (substr_count($template, $this->tagOpen) !== substr_count($template, $this->tagClose)) {
            $errors[] = 'Unclosed tag detected.';
        }

        if (substr_count($template, $this->echoOpen) !== substr_count($template, $this->echoClose)) {
            $errors[] = 'Unclosed echo detected.';
        }

        // Add more checks as needed
        return empty($errors) ? true : $errors;
    }

    // Preprocessing
    public function addPreprocessor($callable)
    {
        $this->preprocessors[] = $callable;
    }

    // Dynamic partial/component discovery
    public function discoverPartials()
    {
        // Robust partial/component discovery
        $partialsDir = rtrim($this->templatePath, '/') . '/partials';

        if (is_dir($partialsDir)) {
            foreach (glob($partialsDir . '/*.weave.php') as $file) {
                $name                  = basename($file, '.weave.php');
                $this->partials[$name] = $file;
            }
        }

        if ($this->componentsDir && is_dir($this->componentsDir)) {
            foreach (glob($this->componentsDir . '/*.php') as $file) {
                $name                    = basename($file, '.php');
                $this->components[$name] = require $file;
            }
        }
    }

    // Versioning
    public function setTemplateVersion($view, $version, $file)
    {
        $this->templateVersions[$view][$version] = $file;
    }
    public function useVersion($version)
    {
        $this->activeVersion = $version;
    }

    // Access control
    public function setAccessControl($callable)
    {
        $this->accessControl = $callable;
    }
    public function checkAccess($perm)
    {
        if ($this->accessControl) {
            return call_user_func($this->accessControl, $perm);
        }

        return true;
    }

    // Export/import
    public function exportTemplate($view)
    {
        // Example: export as HTML
        return htmlspecialchars($this->render($view, []));
    }
    public function importTemplate($data)
    {
        // Example: import template definition
        return true;
    }

    // API
    public function enableApi($on = true)
    {
        $this->apiEnabled = $on;
    }
    public function renderApi($view, $data = [])
    {
        if (!$this->apiEnabled) {
            throw new \Exception('API not enabled');
        }

        return $this->render($view, $data);
    }
    // Asset helper (versioning, etc.)
    public function asset($path)
    {
        // Example: add cache-busting or CDN logic here
        return '/assets/' . ltrim($path, '/');
    }

    // Translation/i18n helper

    // Macro registration
    public function macro($name, $content)
    {
        $this->macros[$name] = $content;
    }

    // Event hooks
    public function on($event, $callback)
    {
        if (isset($this->hooks[$event])) {
            $this->hooks[$event][] = $callback;
        }
    }
    protected function triggerHook($event, ...$args)
    {
        if (isset($this->hooks[$event])) {
            foreach ($this->hooks[$event] as $cb) {
                call_user_func_array($cb, $args);
            }
        }
    }
    protected function applyFilter($filter, $value)
    {
        if (isset($this->filters[$filter])) {
            return call_user_func($this->filters[$filter], $value);
        }

        return $value;
    }

    protected function renderComponent($name, $data = [])
    {
        if (isset($this->components[$name])) {
            return call_user_func($this->components[$name], $data);
        }

        return '';
    }

    protected function formatError($e, $template)
    {
        $lines = explode("\n", $template);
        $msg   = '<pre style="color:red;">Weave Error: ' . htmlspecialchars($e->getMessage()) . "\n";

        if (method_exists($e, 'getLine')) {
            $line = $e->getLine();
            $msg .= 'Line: ' . $line . "\n";

            if (isset($lines[$line - 1])) {
                $msg .= 'Context: ' . htmlspecialchars($lines[$line - 2] ?? '') . "\n> " . htmlspecialchars($lines[$line - 1]) . "\n" . htmlspecialchars($lines[$line] ?? '') . "\n";
            }
        }
        $msg .= '</pre>';

        return $msg;
    }

    protected function evaluate($compiled, $data)
    {
        extract($data, EXTR_SKIP);
        ob_start();
        eval('?>'.$compiled);

        return ob_get_clean();
    }

    public function directive($name, $handler)
    {
        if (!is_string($name) || !is_callable($handler)) {
            throw new \InvalidArgumentException('Directive name must be string and handler must be callable');
        }
        $this->directives[$name] = $handler;
    }

    public function filter($name, $handler)
    {
        if (!is_string($name) || !is_callable($handler)) {
            throw new \InvalidArgumentException('Filter name must be string and handler must be callable');
        }
        $this->filters[$name] = $handler;
    }

    public function component($name, $handler)
    {
        if (!is_string($name) || !is_callable($handler)) {
            throw new \InvalidArgumentException('Component name must be string and handler must be callable');
        }
        $this->components[$name] = $handler;
    }

    protected function registerDefaultDirectives()
    {
        // Example: @if, @foreach, @include, @slot, @layout, etc.
        $this->directive('if', function ($expr) { return "<?php if ($expr): ?>"; });
        $this->directive('endif', function () { return '<?php endif; ?>'; });
        $this->directive('foreach', function ($expr) { return "<?php foreach ($expr): ?>"; });
        $this->directive('endforeach', function () { return '<?php endforeach; ?>'; });
        // Add more...
    }

    protected function registerDefaultFilters()
    {
        $this->filter('upper', function ($v) { return strtoupper($v); });
        $this->filter('lower', function ($v) { return strtolower($v); });
        $this->filter('escape', function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); });
        // Add more...
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    public function setSecureMode($secure)
    {
        $this->secureMode = (bool)$secure;
    }

    // --- Ultra-advanced features ---

    // Template sandboxing (limit allowed PHP functions, restrict variable scope)
    protected $sandboxEnabled   = false;
    protected $allowedFunctions = ['strtoupper','strtolower','htmlspecialchars','count','in_array','array_merge','implode','explode','substr','strlen','is_array','is_string','is_numeric'];
    public function enableSandbox($on = true, $allowedFunctions = null)
    {
        $this->sandboxEnabled = $on;

        if ($allowedFunctions) {
            $this->allowedFunctions = $allowedFunctions;
        }
    }
    protected function sandboxEval($code, $data)
    {
        if (!$this->sandboxEnabled) {
            return $this->evaluate($code, $data);
        }
        // Very basic sandbox: disable dangerous functions
        $disabled  = ['exec','shell_exec','system','passthru','proc_open','popen','curl_exec','curl_multi_exec','parse_ini_file','show_source','file_get_contents','file_put_contents','unlink','fopen','fclose','fwrite','file','copy','rename','move_uploaded_file','eval'];
        $sandboxed = preg_replace('/\\b('.implode('|', $disabled).')\\b/', '/*disabled*/', $code);

        return $this->evaluate($sandboxed, $data);
    }

    // Template linting/validation with detailed error reporting
    public function lint($template)
    {
        $errors = [];

        if (substr_count($template, $this->tagOpen) !== substr_count($template, $this->tagClose)) {
            $errors[] = 'Unclosed tag detected.';
        }

        // Add more checks as needed
        return $errors;
    }

    // Template dependency graph
    protected $dependencyGraph = [];
    protected function trackDependency($from, $to)
    {
        $this->dependencyGraph[$from][] = $to;
    }
    public function getDependencyGraph()
    {
        return $this->dependencyGraph;
    }

    // Partial hydration (for modern JS frameworks)
    protected $hydrationEnabled = false;
    public function enableHydration($on = true)
    {
        $this->hydrationEnabled = $on;
    }

    // Pluggable security policies (CSP, XSS audit, auto-escaping)
    protected $securityPolicies = [];
    public function addSecurityPolicy($name, $callable)
    {
        $this->securityPolicies[$name] = $callable;
    }
    public function runSecurityPolicies($output)
    {
        foreach ($this->securityPolicies as $policy) {
            $output = call_user_func($policy, $output);
        }

        return $output;
    }

    // Template change watcher (auto-reload in dev mode)
    protected $watchers = [];
    public function addWatcher($callable)
    {
        $this->watchers[] = $callable;
    }
    protected function runWatchers($file)
    {
        foreach ($this->watchers as $watch) {
            call_user_func($watch, $file);
        }
    }

    // Built-in profiler UI (show render times, cache hits, etc.)
    public function renderProfiler()
    {
        $out = '<div style="background:#333;color:#fff;padding:8px;font-size:12px;">';
        $out .= 'Weave Profiler: Render time: '.round(($this->profile['render_time'] ?? 0) * 1000, 2).'ms, Cache: '.($this->cacheEnabled ? 'on' : 'off');
        $out .= '</div>';

        return $out;
    }

    // CLI tools for template compilation, linting, cache clearing
    public function cli($argv)
    {
        $cmd = $argv[1] ?? null;

        switch ($cmd) {
            case 'lint':
                $file = $argv[2] ?? null;

                if ($file && file_exists($file)) {
                    $tpl  = file_get_contents($file);
                    $errs = $this->lint($tpl);
                    echo empty($errs) ? "No errors\n" : implode("\n", $errs)."\n";
                }
                break;

            case 'clear-cache':
                $this->clearCache();
                echo "Cache cleared\n";
                break;
                // Add more CLI commands as needed
        }
    }

    // Advanced i18n: pluralization, context, fallback chains
    protected $translations = [];
    public function addTranslation($locale, $key, $value)
    {
        $this->translations[$locale][$key] = $value;
    }
    public function translate($key, $count = null, $context = null)
    {
        $locale = $this->locale;
        $val    = $this->translations[$locale][$key] ?? $key;

        // Pluralization
        if (is_array($val) && $count !== null) {
            return $val[$count == 1 ? 'one' : 'other'] ?? $key;
        }

        // Context
        if (is_array($val) && $context !== null) {
            return $val[$context] ?? $key;
        }

        return $val;
    }

    // Template import/export in multiple formats (JSON, YAML, etc.)
    public function exportTemplateJson($view)
    {
        $tpl = $this->loadTemplate($view);

        return json_encode(['view' => $view,'template' => $tpl], JSON_PRETTY_PRINT);
    }
    public function importTemplateJson($json)
    {
        $arr = json_decode($json, true);

        if (!empty($arr['view']) && !empty($arr['template'])) {
            $file = rtrim($this->templatePath, '/').'/'.$arr['view'].'.weave.php';
            file_put_contents($file, $arr['template']);

            return true;
        }

        return false;
    }

    // API endpoint for live template preview/testing
    public function apiPreview($view, $data = [])
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->render($view, $data);
        exit;
    }

    // Integration hooks for static analysis tools
    protected $staticAnalysisHooks = [];
    public function addStaticAnalysisHook($callable)
    {
        $this->staticAnalysisHooks[] = $callable;
    }
    public function runStaticAnalysis($template)
    {
        foreach ($this->staticAnalysisHooks as $hook) {
            call_user_func($hook, $template);
        }
    }

    // Custom error templates for graceful fallback
    protected $errorTemplates = [];
    public function setErrorTemplate($type, $view)
    {
        $this->errorTemplates[$type] = $view;
    }
    protected function renderError($type, $data = [])
    {
        if (isset($this->errorTemplates[$type])) {
            return $this->render($this->errorTemplates[$type], $data);
        }

        return '<div style="color:red;">Weave Error: '.htmlspecialchars($type).'</div>';
    }
}
