<?php

declare(strict_types=1);

namespace Synchrenity\Pagination;

/**
 * SynchrenityPaginator: Robust, secure, flexible, and extensible pagination
 */
class SynchrenityPaginator
{
    // --- ADVANCED: Cursor pagination state ---
    protected $cursor     = null;
    protected $nextCursor = null;
    protected $prevCursor = null;

    // --- ADVANCED: Event hooks ---
    protected $hooks = [];
    public function addHook($event, callable $cb)
    {
        $this->hooks[$event][] = $cb;
    }
    protected function triggerHook($event)
    {
        foreach ($this->hooks[$event] ?? [] as $cb) {
            call_user_func($cb, $this);
        }
    }

    // --- ADVANCED: Access control ---
    protected $accessCallback = null;
    public function setAccessCallback(callable $cb)
    {
        $this->accessCallback = $cb;
    }
    protected function checkAccess($item)
    {
        return $this->accessCallback ? call_user_func($this->accessCallback, $item) : true;
    }

    // --- ADVANCED: Caching (pluggable) ---
    protected $cache = null;
    public function setCache($cache)
    {
        $this->cache = $cache;
    }
    protected function cacheGet($key)
    {
        return $this->cache ? $this->cache->get($key) : null;
    }
    protected function cacheSet($key, $value, $ttl = 60)
    {
        if ($this->cache) {
            $this->cache->set($key, $value, $ttl);
        }
    }

    // --- ADVANCED: Async/streaming pagination (stub) ---
    public function stream(callable $cb)
    {
        foreach ($this->data as $item) {
            $cb($item);
        }
    }

    // --- ADVANCED: Extensible filters/sorters ---
    protected $filters = [];
    protected $sorters = [];
    public function addFilter(callable $cb)
    {
        $this->filters[] = $cb;
    }
    public function addSorter(callable $cb)
    {
        $this->sorters[] = $cb;
    }
    protected function applyFilters($data)
    {
        foreach ($this->filters as $f) {
            $data = array_filter($data, $f);
        }

        return $data;
    }
    protected function applySorters($data)
    {
        foreach ($this->sorters as $s) {
            usort($data, $s);
        }

        return $data;
    }

    // --- ADVANCED: Security (output escaping, XSS, etc) ---
    protected function escape($str)
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // --- ADVANCED: Multi-format output (XML, CSV, etc) ---
    public function toXml()
    {
        $xml  = new \SimpleXMLElement('<pagination/>');
        $meta = $xml->addChild('meta');

        foreach ($this->meta as $k => $v) {
            $meta->addChild($k, (string)$v);
        }
        $data = $xml->addChild('data');

        foreach ($this->data as $item) {
            $row = $data->addChild('item');

            foreach ((array)$item as $k => $v) {
                $row->addChild($k, (string)$v);
            }
        }

        return $xml->asXML();
    }
    public function toCsv()
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_keys((array)($this->data[0] ?? [])));

        foreach ($this->data as $item) {
            fputcsv($out, (array)$item);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    // --- ADVANCED: Plugin support ---
    protected $plugins = [];
    public function registerPlugin($plugin)
    {
        if (is_callable([$plugin, 'register'])) {
            $plugin->register($this);
        }
        $this->plugins[] = $plugin;
    }

    // --- ADVANCED: Introspection ---
    public function getMeta()
    {
        return $this->meta;
    }
    public function getData()
    {
        return $this->data;
    }
    public function getPage()
    {
        return $this->page;
    }
    public function getPerPage()
    {
        return $this->perPage;
    }
    public function getLastPage()
    {
        return $this->lastPage;
    }
    public function getRoute()
    {
        return $this->route;
    }
    public function getParams()
    {
        return $this->params;
    }
    protected $data;
    protected $total;
    protected $page;
    protected $perPage;
    protected $lastPage;
    protected $route;
    protected $params = [];
    protected $meta   = [];
    protected $sortFilter;

    public function __construct($data, $total, $page = 1, $perPage = 15, $route = null, $params = [], $sortFilter = null)
    {
        $this->sortFilter = $sortFilter;

        if ($sortFilter) {
            $data = $sortFilter->apply($data);
        }
        $this->data     = $data;
        $this->total    = max(0, (int)$total);
        $this->page     = max(1, (int)$page);
        $this->perPage  = max(1, min(100, (int)$perPage));
        $this->lastPage = (int)ceil($this->total / $this->perPage);
        $this->route    = $route;
        $this->params   = $params;
        $this->meta     = [
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->page,
            'last_page'    => $this->lastPage,
            'from'         => ($this->page - 1) * $this->perPage + 1,
            'to'           => min($this->page * $this->perPage, $this->total),
        ];
    }

    public static function fromArray(array $items, $page = 1, $perPage = 15, $route = null, $params = [], $sortFilter = null)
    {
        if ($sortFilter) {
            $items = $sortFilter->apply($items);
        }
        $total    = count($items);
        $offset   = ($page - 1) * $perPage;
        $data     = array_slice($items, $offset, $perPage);
        $instance = new self($data, $total, $page, $perPage, $route, $params, $sortFilter);
        $instance->triggerHook('paginate');

        return $instance;
    }

    public function links()
    {
        $links = [];

        for ($i = 1; $i <= $this->lastPage; $i++) {
            $url     = $this->route ? $this->route . '?' . http_build_query(array_merge($this->params, ['page' => $i, 'perPage' => $this->perPage])) : '#';
            $links[] = [
                'page'   => $i,
                'url'    => $url,
                'active' => $i === $this->page,
            ];
        }

        // Add prev/next/first/last
        if ($this->page > 1) {
            array_unshift($links, [
                'page'   => 'first',
                'url'    => $this->route ? $this->route . '?' . http_build_query(array_merge($this->params, ['page' => 1, 'perPage' => $this->perPage])) : '#',
                'active' => false,
            ]);
            array_unshift($links, [
                'page'   => 'prev',
                'url'    => $this->route ? $this->route . '?' . http_build_query(array_merge($this->params, ['page' => $this->page - 1, 'perPage' => $this->perPage])) : '#',
                'active' => false,
            ]);
        }

        if ($this->page < $this->lastPage) {
            $links[] = [
                'page'   => 'next',
                'url'    => $this->route ? $this->route . '?' . http_build_query(array_merge($this->params, ['page' => $this->page + 1, 'perPage' => $this->perPage])) : '#',
                'active' => false,
            ];
            $links[] = [
                'page'   => 'last',
                'url'    => $this->route ? $this->route . '?' . http_build_query(array_merge($this->params, ['page' => $this->lastPage, 'perPage' => $this->perPage])) : '#',
                'active' => false,
            ];
        }

        return $links;
    }

    public function toArray()
    {
        return [
            'data'  => $this->data,
            'meta'  => $this->meta,
            'links' => $this->links(),
        ];
    }

    public function toJson()
    {
        return json_encode($this->toArray());
    }

    public function toHtml()
    {
        $html = '<nav class="pagination"><ul>';

        foreach ($this->links() as $link) {
            $html .= '<li' . ($link['active'] ? ' class="active"' : '') . '>';
            $html .= '<a href="' . $this->escape($link['url']) . '">' . $this->escape($link['page']) . '</a>';
            $html .= '</li>';
        }
        $html .= '</ul></nav>';

        return $html;
    }

    // --- ADVANCED: Cursor pagination ---
    public static function fromCursor($items, $cursor, $perPage = 15, $route = null, $params = [], $sortFilter = null)
    {
        if ($sortFilter) {
            $items = $sortFilter->apply($items);
        }
        $start = 0;

        if ($cursor !== null) {
            foreach ($items as $i => $item) {
                if (isset($item['id']) && $item['id'] == $cursor) {
                    $start = $i + 1;
                    break;
                }
            }
        }
        $data                 = array_slice($items, $start, $perPage);
        $nextCursor           = isset($items[$start + $perPage]['id']) ? $items[$start + $perPage]['id'] : null;
        $prevCursor           = $cursor;
        $instance             = new self($data, count($items), 1, $perPage, $route, $params, $sortFilter);
        $instance->cursor     = $cursor;
        $instance->nextCursor = $nextCursor;
        $instance->prevCursor = $prevCursor;
        $instance->triggerHook('paginate');

        return $instance;
    }

    // --- ADVANCED: Event hooks registration ---
    public function onPaginate($callback)
    {
        $this->addHook('paginate', $callback);
    }

    // Sorting, filtering, access control stubs can be added here
}
