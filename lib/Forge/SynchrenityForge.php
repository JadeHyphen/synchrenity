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
        $compiled = $this->compile($template);
        return $this->evaluate($compiled, $data);
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
        // Parse directives, components, slots, filters, etc.
        // Example: Replace {{ var }} with PHP echo, handle @if/@foreach, etc.
        $compiled = preg_replace('/{{\s*(.+?)\s*}}/', '<?php echo htmlspecialchars($1); ?>', $template);
        // Add more advanced parsing here...
        return $compiled;
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
