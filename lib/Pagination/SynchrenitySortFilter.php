<?php
namespace Synchrenity\Pagination;

/**
 * SynchrenitySortFilter: Powerful sorting, filtering, and access control for paginated data
 */
class SynchrenitySortFilter
{
    // --- ADVANCED: Custom comparators and sort strategies ---
    protected $comparators = [];
    public function setComparator($field, callable $comparator) {
        $this->comparators[$field] = $comparator;
        return $this;
    }

    // --- ADVANCED: Complex filters (range, in, not, regex, null) ---
    protected $complexFilters = [];
    public function filterRange($field, $min, $max) {
        $this->complexFilters[] = function($item) use ($field, $min, $max) {
            return isset($item[$field]) && $item[$field] >= $min && $item[$field] <= $max;
        }; return $this;
    }
    public function filterIn($field, array $values) {
        $this->complexFilters[] = function($item) use ($field, $values) {
            return isset($item[$field]) && in_array($item[$field], $values);
        }; return $this;
    }
    public function filterNot($field, $value) {
        $this->complexFilters[] = function($item) use ($field, $value) {
            return !isset($item[$field]) || $item[$field] != $value;
        }; return $this;
    }
    public function filterRegex($field, $pattern) {
        $this->complexFilters[] = function($item) use ($field, $pattern) {
            return isset($item[$field]) && preg_match($pattern, $item[$field]);
        }; return $this;
    }
    public function filterNull($field) {
        $this->complexFilters[] = function($item) use ($field) {
            return !isset($item[$field]) || $item[$field] === null;
        }; return $this;
    }

    // --- ADVANCED: Search (fulltext/partial) ---
    protected $searchTerm = null;
    protected $searchFields = [];
    public function search($term, array $fields) {
        $this->searchTerm = $term;
        $this->searchFields = $fields;
        return $this;
    }

    // --- ADVANCED: Pluggable filter/sort strategies ---
    protected $filterStrategies = [];
    protected $sortStrategies = [];
    public function addFilterStrategy(callable $cb) { $this->filterStrategies[] = $cb; return $this; }
    public function addSortStrategy(callable $cb) { $this->sortStrategies[] = $cb; return $this; }

    // --- ADVANCED: Event hooks ---
    protected $hooks = [];
    public function addHook($event, callable $cb) { $this->hooks[$event][] = $cb; return $this; }
    protected function triggerHook($event, $data) {
        foreach ($this->hooks[$event] ?? [] as $cb) call_user_func($cb, $data, $this);
    }

    // --- ADVANCED: Plugin support ---
    protected $plugins = [];
    public function registerPlugin($plugin) {
        if (is_callable([$plugin, 'register'])) $plugin->register($this);
        $this->plugins[] = $plugin;
        return $this;
    }

    // --- ADVANCED: Introspection ---
    public function getSort() { return $this->sort; }
    public function getFilters() { return $this->filters; }
    public function getComplexFilters() { return $this->complexFilters; }
    public function getSearch() { return [$this->searchTerm, $this->searchFields]; }
    protected $sort = [];
    protected $filters = [];
    protected $accessCallback = null;

    public function sortBy($field, $direction = 'asc')
    {
        $this->sort[$field] = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        return $this;
    }

    public function filter($field, $value)
    {
        $this->filters[$field] = $value;
        return $this;
    }

    public function setAccessControl(callable $callback)
    {
        $this->accessCallback = $callback;
        return $this;
    }

    public function apply(array $items)
    {
        // Basic filters
        foreach ($this->filters as $field => $value) {
            $items = array_filter($items, function($item) use ($field, $value) {
                return isset($item[$field]) && $item[$field] == $value;
            });
        }
        // Complex filters
        foreach ($this->complexFilters as $filter) {
            $items = array_filter($items, $filter);
        }
        // Search
        if ($this->searchTerm && $this->searchFields) {
            $term = strtolower($this->searchTerm);
            $fields = $this->searchFields;
            $items = array_filter($items, function($item) use ($term, $fields) {
                foreach ($fields as $f) {
                    if (isset($item[$f]) && stripos((string)$item[$f], $term) !== false) return true;
                }
                return false;
            });
        }
        // Pluggable filter strategies
        foreach ($this->filterStrategies as $cb) {
            $items = $cb($items, $this);
        }
        // Sorting (multi-field, custom comparators)
        if (!empty($this->sort)) {
            $sortFields = array_reverse($this->sort);
            $self = $this;
            usort($items, function($a, $b) use ($sortFields, $self) {
                foreach ($sortFields as $field => $direction) {
                    $av = $a[$field] ?? null;
                    $bv = $b[$field] ?? null;
                    if ($av === $bv) continue;
                    if (isset($self->comparators[$field])) {
                        $cmp = call_user_func($self->comparators[$field], $av, $bv);
                    } else {
                        $cmp = ($av <=> $bv);
                    }
                    return ($direction === 'asc' ? 1 : -1) * $cmp;
                }
                return 0;
            });
        }
        // Pluggable sort strategies
        foreach ($this->sortStrategies as $cb) {
            $items = $cb($items, $this);
        }
        // Access control
        if ($this->accessCallback) {
            $items = array_filter($items, $this->accessCallback);
        }
        $items = array_values($items);
        $this->triggerHook('applied', $items);
        return $items;
    }
}
