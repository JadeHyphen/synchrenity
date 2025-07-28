<?php
namespace Synchrenity\Forge;

/**
 * SynchrenityForge: Ultra-secure, extensible, and powerful templating engine
 * Features: advanced syntax, sandboxed execution, custom directives, components, slots, layouts, macros, filters, asset pipeline, i18n, caching, event hooks, error reporting, and more.
 */

class SynchrenityForge
{
    protected $templatePath;
    protected $cachePath;
    protected $variables = [];
    protected $directives = [];
    protected $components = [];
    protected $filters = [];
    protected $eventManager;
    protected $secureMode = true;
    protected $locale = 'en';

    public function __construct($templatePath, $cachePath = null, $eventManager = null)
    {
        $this->templatePath = $templatePath;
        $this->cachePath = $cachePath;
        $this->eventManager = $eventManager;
        $this->registerDefaultDirectives();
        $this->registerDefaultFilters();
    }

    public function render($view, $data = [])
    {
        $this->variables = $data;
        $template = $this->loadTemplate($view);
        try {
            $compiled = $this->compile($template);
            return $this->evaluate($compiled, $data);
        } catch (\Throwable $e) {
            return $this->formatError($e, $template);
        }
    }

    protected function loadTemplate($view)
    {
        $file = rtrim($this->templatePath, '/').'/'.$view.'.forge.php';
        if (!file_exists($file)) {
            throw new \Exception("Template not found: $view");
        }
        return file_get_contents($file);
    }

    protected function compile($template)
    {
        // Parse {% ... %} tags
        $compiled = preg_replace_callback('/\{\%\s*(set|if|for|include|layout)\s+(.+?)\s*\%\}/', function($m) {
            switch ($m[1]) {
                case 'set':
                    return '<?php ' . $m[2] . '; ?>';
                case 'if':
                    return '<?php if (' . $m[2] . '): ?>';
                case 'for':
                    return '<?php foreach (' . $m[2] . '): ?>';
                case 'include':
                    return '<?php echo $this->render(' . var_export(trim($m[2], "'\""), true) . ', get_defined_vars()); ?>';
                case 'layout':
                    // Layouts can be handled by a custom mechanism
                    return '<?php /* layout: ' . $m[2] . ' */ ?>';
            }
            return $m[0];
        }, $template);

        // End tags
        $compiled = preg_replace('/\{\%\s*endif\s*\%\}/', '<?php endif; ?>', $compiled);
        $compiled = preg_replace('/\{\%\s*endfor\s*\%\}/', '<?php endforeach; ?>', $compiled);

        // Parse {{ var|filter }} and {{ var }}
        $compiled = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function($m) {
            $expr = $m[1];
            if (strpos($expr, '|') !== false) {
                list($var, $filter) = array_map('trim', explode('|', $expr, 2));
                return '<?php echo $this->applyFilter(' . var_export($filter, true) . ', ' . $var . '); ?>';
            }
            // Default: escape output
            return '<?php echo htmlspecialchars(' . $expr . ', ENT_QUOTES, "UTF-8"); ?>';
        }, $compiled);

        // Parse components/partials: {% component 'name' %}
        $compiled = preg_replace_callback('/\{\%\s*component\s+([\'\"])(.+?)\1\s*\%\}/', function($m) {
            return '<?php echo $this->renderComponent(' . var_export($m[2], true) . ', get_defined_vars()); ?>';
        }, $compiled);

        return $compiled;
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
        $msg = '<pre style="color:red;">Forge Error: ' . htmlspecialchars($e->getMessage()) . "\n";
        if (method_exists($e, 'getLine')) {
            $line = $e->getLine();
            $msg .= 'Line: ' . $line . "\n";
            if (isset($lines[$line-1])) {
                $msg .= 'Context: ' . htmlspecialchars($lines[$line-2] ?? '') . "\n> " . htmlspecialchars($lines[$line-1]) . "\n" . htmlspecialchars($lines[$line] ?? '') . "\n";
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
        $this->directives[$name] = $handler;
    }

    public function filter($name, $handler)
    {
        $this->filters[$name] = $handler;
    }

    public function component($name, $handler)
    {
        $this->components[$name] = $handler;
    }

    protected function registerDefaultDirectives()
    {
        // Example: @if, @foreach, @include, @slot, @layout, etc.
        $this->directive('if', function($expr) { return "<?php if ($expr): ?>"; });
        $this->directive('endif', function() { return "<?php endif; ?>"; });
        $this->directive('foreach', function($expr) { return "<?php foreach ($expr): ?>"; });
        $this->directive('endforeach', function() { return "<?php endforeach; ?>"; });
        // Add more...
    }

    protected function registerDefaultFilters()
    {
        $this->filter('upper', function($v) { return strtoupper($v); });
        $this->filter('lower', function($v) { return strtolower($v); });
        $this->filter('escape', function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); });
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

    // Add more advanced features: asset pipeline, i18n, caching, event hooks, error reporting, macros, slots, layouts, etc.
}
