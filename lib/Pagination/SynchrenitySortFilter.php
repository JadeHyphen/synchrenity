<?php
namespace Synchrenity\Pagination;

/**
 * SynchrenitySortFilter: Powerful sorting, filtering, and access control for paginated data
 */
class SynchrenitySortFilter
{
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
        // Filtering
        foreach ($this->filters as $field => $value) {
            $items = array_filter($items, function($item) use ($field, $value) {
                return isset($item[$field]) && $item[$field] == $value;
            });
        }
        // Sorting
        foreach (array_reverse($this->sort) as $field => $direction) {
            usort($items, function($a, $b) use ($field, $direction) {
                return ($direction === 'asc' ? 1 : -1) * strcmp($a[$field] ?? '', $b[$field] ?? '');
            });
        }
        // Access control
        if ($this->accessCallback) {
            $items = array_filter($items, $this->accessCallback);
        }
        return array_values($items);
    }
}
