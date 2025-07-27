<?php
namespace Synchrenity\Pagination;

/**
 * SynchrenityPaginator: Robust, secure, flexible, and extensible pagination
 */
class SynchrenityPaginator
{
    protected $data;
    protected $total;
    protected $page;
    protected $perPage;
    protected $lastPage;
    protected $route;
    protected $params = [];
    protected $meta = [];
    protected $sortFilter;

    public function __construct($data, $total, $page = 1, $perPage = 15, $route = null, $params = [], $sortFilter = null)
    {
        $this->sortFilter = $sortFilter;
        if ($sortFilter) {
            $data = $sortFilter->apply($data);
        }
        $this->data = $data;
        $this->total = max(0, (int)$total);
        $this->page = max(1, (int)$page);
        $this->perPage = max(1, min(100, (int)$perPage));
        $this->lastPage = (int)ceil($this->total / $this->perPage);
        $this->route = $route;
        $this->params = $params;
        $this->meta = [
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->page,
            'last_page' => $this->lastPage,
            'from' => ($this->page - 1) * $this->perPage + 1,
            'to' => min($this->page * $this->perPage, $this->total)
        ];
    }

    public static function fromArray(array $items, $page = 1, $perPage = 15, $route = null, $params = [], $sortFilter = null)
    {
        if ($sortFilter) {
            $items = $sortFilter->apply($items);
        }
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $data = array_slice($items, $offset, $perPage);
        return new self($data, $total, $page, $perPage, $route, $params, $sortFilter);
    }

    public function links()
    {
        $links = [];
        for ($i = 1; $i <= $this->lastPage; $i++) {
            $url = $this->route ? $this->route . '?' . http_build_query(array_merge($this->params, ['page' => $i, 'perPage' => $this->perPage])) : '#';
            $links[] = [
                'page' => $i,
                'url' => $url,
                'active' => $i === $this->page
            ];
        }
        return $links;
    }

    public function toArray()
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta,
            'links' => $this->links()
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
            $html .= '<a href="' . htmlspecialchars($link['url']) . '">' . $link['page'] . '</a>';
            $html .= '</li>';
        }
        $html .= '</ul></nav>';
        return $html;
    }

    // Cursor pagination stub
    public static function fromCursor($items, $cursor, $perPage = 15, $route = null, $params = [])
    {
        // Implement cursor logic here
        return new self($items, count($items), 1, $perPage, $route, $params);
    }

    // Event hooks stub
    public function onPaginate($callback)
    {
        // Call $callback($this) after pagination
    }

    // Sorting, filtering, access control stubs can be added here
}
