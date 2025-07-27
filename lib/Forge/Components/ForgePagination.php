<?php
namespace Synchrenity\Forge\Components;

use Synchrenity\Pagination\SynchrenityPaginator;

/**
 * ForgePagination: Renders paginated navigation and metadata for SynchrenityPaginator
 */
class ForgePagination
{
    public static function render(SynchrenityPaginator $paginator, $options = [])
    {
        $links = $paginator->links();
        $meta = $paginator->toArray()['meta'];
        $html = '<nav class="pagination-nav">';
        // Previous link
        if ($meta['current_page'] > 1) {
            $prevUrl = $links[$meta['current_page'] - 2]['url'] ?? '#';
            $html .= '<a href="'.$prevUrl.'" class="page-prev">Prev</a>';
        }
        foreach ($links as $link) {
            $active = $link['active'] ? 'active' : '';
            $html .= '<a href="'.$link['url'].'" class="page-num '.$active.'">'.$link['page'].'</a>';
        }
        // Next link
        if ($meta['current_page'] < $meta['last_page']) {
            $nextUrl = $links[$meta['current_page']]['url'] ?? '#';
            $html .= '<a href="'.$nextUrl.'" class="page-next">Next</a>';
        }
        $html .= '</nav>';
        // Optionally add metadata for SEO
        if (!empty($options['seo'])) {
            $canonical = $links[$meta['current_page'] - 1]['url'] ?? '';
            $html .= '<link rel="canonical" href="'.$canonical.'">';
            if ($meta['current_page'] > 1) $html .= '<link rel="prev" href="'.$prevUrl.'">';
            if ($meta['current_page'] < $meta['last_page']) $html .= '<link rel="next" href="'.$nextUrl.'">';
        }
        return $html;
    }
}
