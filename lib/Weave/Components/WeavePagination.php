<?php

declare(strict_types=1);

namespace Synchrenity\Weave\Components;

use Synchrenity\Pagination\SynchrenityPaginator;

/**
 * WeavePagination: Renders paginated navigation and metadata for SynchrenityPaginator
 */
class WeavePagination
{
    /**
     * Render robust, accessible, customizable pagination navigation.
     * Options:
     *   - 'aria_label': string (default: 'Pagination Navigation')
     *   - 'class': string (nav class)
     *   - 'ul_class': string (ul class)
     *   - 'li_class': string (li class)
     *   - 'a_class': string (a class)
     *   - 'active_class': string (active class)
     *   - 'disabled_class': string (disabled class)
     *   - 'show_first_last': bool
     *   - 'show_ellipsis': bool
     *   - 'seo': bool
     *   - 'show_numbers': bool
     *   - 'show_prev_next': bool
     *   - 'aria_current': bool
     */
    public static function render(SynchrenityPaginator $paginator, $options = [])
    {
        $links = $paginator->links();
        $meta  = $paginator->toArray()['meta'];

        // API-driven UI: output as JSON if requested
        if (!empty($options['as_json'])) {
            $json = [
                'meta'    => $meta,
                'links'   => $links,
                'options' => $options,
            ];

            // Optionally include data if requested
            if (!empty($options['include_data'])) {
                $json['data'] = $paginator->toArray()['data'];
            }

            return json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $ariaLabel       = $options['aria_label']        ?? 'Pagination Navigation';
        $navClass        = $options['class']             ?? 'pagination-nav';
        $ulClass         = $options['ul_class']          ?? 'pagination-list';
        $liClass         = $options['li_class']          ?? 'pagination-item';
        $aClass          = $options['a_class']           ?? 'pagination-link';
        $activeClass     = $options['active_class']      ?? 'active';
        $disabledClass   = $options['disabled_class']    ?? 'disabled';
        $theme           = $options['theme']             ?? 'auto'; // 'auto', 'dark', 'light'
        $responsive      = $options['responsive']        ?? true;
        $ariaRole        = $options['aria_role']         ?? 'navigation';
        $buttonElement   = $options['button_element']    ?? 'a'; // 'a' or 'button'
        $ssrFallback     = $options['ssr_fallback']      ?? false;
        $showFirstLast   = $options['show_first_last']   ?? true;
        $showEllipsis    = $options['show_ellipsis']     ?? true;
        $showNumbers     = $options['show_numbers']      ?? true;
        $showPrevNext    = $options['show_prev_next']    ?? true;
        $ariaCurrent     = $options['aria_current']      ?? true;
        $showPageSize    = $options['show_page_size']    ?? false;
        $pageSizeOptions = $options['page_size_options'] ?? [10, 25, 50, 100];
        $showTotal       = $options['show_total']        ?? true;
        $labelPrev       = $options['label_prev']        ?? 'Prev';
        $labelNext       = $options['label_next']        ?? 'Next';
        $labelFirst      = $options['label_first']       ?? 'First';
        $labelLast       = $options['label_last']        ?? 'Last';
        $iconPrev        = $options['icon_prev']         ?? '';
        $iconNext        = $options['icon_next']         ?? '';
        $iconFirst       = $options['icon_first']        ?? '';
        $iconLast        = $options['icon_last']         ?? '';
        $customRender    = $options['custom_render']     ?? null;

        $themeClass      = $theme === 'dark' ? 'pagination-dark' : ($theme === 'light' ? 'pagination-light' : '');
        $responsiveClass = $responsive ? 'pagination-responsive' : '';
        $html            = '<nav class="'.$navClass.' '.$themeClass.' '.$responsiveClass.'" role="'.$ariaRole.'" aria-label="'.htmlspecialchars($ariaLabel).'" aria-live="polite">';
        $html .= '<ul class="'.$ulClass.'" tabindex="0" data-pagination="true">';

        // Loading state
        if (!empty($options['loading'])) {
            $html .= '<div class="pagination-loading">'.htmlspecialchars($options['loading']).'</div>';
        }

        // AJAX support: add data attributes for JS
        if (!empty($options['ajax'])) {
            $html .= '<script>(function(){
                var nav = document.querySelector("nav."+"'.$navClass.'");
                if(nav){
                    nav.addEventListener("click",function(e){
                        var t=e.target;if(t.tagName==="A"&&t.closest("[data-pagination]")&&t.href){
                            e.preventDefault();
                            if(window.WeavePaginationAjax) window.WeavePaginationAjax(t.href,nav);
                        }
                    });
                }
            })();</script>';
        }

        // Keyboard navigation (left/right arrows)
        $html .= '<script>(function(){
            var ul=document.querySelector("ul."+"'.$ulClass.'");
            if(!ul)return;
            ul.addEventListener("keydown",function(e){
                var links=ul.querySelectorAll("a");
                var idx=Array.prototype.indexOf.call(links,document.activeElement);
                if(e.key==="ArrowRight"&&idx+1<links.length){links[idx+1].focus();e.preventDefault();}
                if(e.key==="ArrowLeft"&&idx-1>=0){links[idx-1].focus();e.preventDefault();}
            });
        })();</script>';

        // Customizable templates for items (future: allow user to pass callbacks)
        // Analytics hook
        if (!empty($options['analytics']) && is_callable($options['analytics'])) {
            call_user_func($options['analytics'], $meta, $links, $options);
        }

        // First page
        if ($showFirstLast && $meta['current_page'] > 1) {
            $firstTag = $buttonElement === 'button' ? '<button type="button" class="'.$aClass.'">'.($iconFirst ?: $labelFirst).'</button>' : '<a href="'.$links[0]['url'].'" class="'.$aClass.'">'.($iconFirst ?: $labelFirst).'</a>';
            $html .= '<li class="'.$liClass.' '.$disabledClass.'">'.$firstTag.'</li>';
        }

        // Previous link
        if ($showPrevNext) {
            if ($meta['current_page'] > 1) {
                $prevUrl = $links[$meta['current_page'] - 2]['url'] ?? '#';
                $prevTag = $buttonElement === 'button' ? '<button type="button" class="'.$aClass.' page-prev">'.($iconPrev ?: $labelPrev).'</button>' : '<a href="'.$prevUrl.'" class="'.$aClass.' page-prev" rel="prev">'.($iconPrev ?: $labelPrev).'</a>';
                $html .= '<li class="'.$liClass.'">'.$prevTag.'</li>';
            } else {
                $html .= '<li class="'.$liClass.' '.$disabledClass.'"><span class="'.$aClass.' page-prev">'.($iconPrev ?: $labelPrev).'</span></li>';
            }
        }

        // Ellipsis before
        if ($showEllipsis && $meta['current_page'] > 3) {
            $html .= '<li class="'.$liClass.' '.$disabledClass.'"><span class="ellipsis">&hellip;</span></li>';
        }

        // Page numbers
        if ($showNumbers) {
            $start = max(1, $meta['current_page'] - 2);
            $end   = min($meta['last_page'], $meta['current_page'] + 2);

            for ($i = $start; $i <= $end; $i++) {
                $link     = $links[$i - 1];
                $isActive = $link['active'];
                $classes  = $liClass;

                if ($isActive) {
                    $classes .= ' '.$activeClass;
                }
                $aria = $isActive && $ariaCurrent ? ' aria-current="page"' : '';
                $html .= '<li class="'.$classes.'">';

                if ($isActive) {
                    $html .= '<span class="'.$aClass.'"'.$aria.'>'.$link['page'].'</span>';
                } else {
                    $pageTag = $buttonElement === 'button' ? '<button type="button" class="'.$aClass.'">'.$link['page'].'</button>' : '<a href="'.$link['url'].'" class="'.$aClass.'">'.$link['page'].'</a>';
                    $html .= $pageTag;
                }
                $html .= '</li>';
            }
        }

        // Ellipsis after
        if ($showEllipsis && $meta['current_page'] < $meta['last_page'] - 2) {
            $html .= '<li class="'.$liClass.' '.$disabledClass.'"><span class="ellipsis">&hellip;</span></li>';
        }

        // Next link
        if ($showPrevNext) {
            if ($meta['current_page'] < $meta['last_page']) {
                $nextUrl = $links[$meta['current_page']]['url'] ?? '#';
                $nextTag = $buttonElement === 'button' ? '<button type="button" class="'.$aClass.' page-next">'.($iconNext ?: $labelNext).'</button>' : '<a href="'.$nextUrl.'" class="'.$aClass.' page-next" rel="next">'.($iconNext ?: $labelNext).'</a>';
                $html .= '<li class="'.$liClass.'">'.$nextTag.'</li>';
            } else {
                $html .= '<li class="'.$liClass.' '.$disabledClass.'"><span class="'.$aClass.' page-next">'.($iconNext ?: $labelNext).'</span></li>';
            }
        }

        // Last page
        if ($showFirstLast && $meta['current_page'] < $meta['last_page']) {
            $lastTag = $buttonElement === 'button' ? '<button type="button" class="'.$aClass.'">'.($iconLast ?: $labelLast).'</button>' : '<a href="'.$links[$meta['last_page'] - 1]['url'].'" class="'.$aClass.'">'.($iconLast ?: $labelLast).'</a>';
            $html .= '<li class="'.$liClass.' '.$disabledClass.'">'.$lastTag.'</li>';
        }

        // Responsive CSS (inline for demo, move to stylesheet in production)
        if ($responsive) {
            $html .= '<style>@media (max-width:600px){.pagination-list{flex-wrap:wrap;}.pagination-item{margin:2px;}}</style>';
        }

        // Theme CSS (inline for demo)
        if ($theme === 'dark') {
            $html .= '<style>.pagination-dark{background:#222;color:#fff;}.pagination-dark a{color:#fff;}</style>';
        } elseif ($theme === 'light') {
            $html .= '<style>.pagination-light{background:#fff;color:#222;}.pagination-light a{color:#222;}</style>';
        }

        // Accessibility: announce page change
        $html .= '<div id="pagination-announcer" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-live="assertive"></div>';
        $html .= '<script>(function(){
            var ul=document.querySelector("ul."+"'.$ulClass.'");
            if(!ul)return;
            ul.addEventListener("click",function(e){
                var t=e.target;if(t.tagName==="A"||t.tagName==="BUTTON"){var p=t.textContent;var ann=document.getElementById("pagination-announcer");if(ann)ann.textContent="Page "+p+" selected.";}
            });
        })();</script>';

        // SSR fallback
        if ($ssrFallback) {
            $html .= '<noscript><div class="pagination-ssr">'.htmlspecialchars('Pagination requires JavaScript for full functionality.').'</div></noscript>';
        }

        $html .= '</ul>';

        // Page size selector
        if ($showPageSize) {
            $html .= '<form method="get" class="pagination-size-form" style="display:inline-block;margin-left:1em;">';
            $html .= '<label for="page-size-select">Page size:</label> ';
            $html .= '<select id="page-size-select" name="per_page" onchange="this.form.submit()">';

            foreach ($pageSizeOptions as $size) {
                $selected = ($meta['per_page'] == $size) ? ' selected' : '';
                $html .= '<option value="'.$size.'"'.$selected.'>'.$size.'</option>';
            }
            $html .= '</select>';
            $html .= '</form>';
        }

        // Total info
        if ($showTotal) {
            $html .= '<span class="pagination-total">Page '.$meta['current_page'].' of '.$meta['last_page'].' ('.$meta['total'].' total)</span>';
        }

        $html .= '</nav>';

        // Optionally add metadata for SEO
        if (!empty($options['seo'])) {
            $canonical = $links[$meta['current_page'] - 1]['url'] ?? '';
            $html .= '<link rel="canonical" href="'.$canonical.'">';

            if ($meta['current_page'] > 1) {
                $html .= '<link rel="prev" href="'.$links[$meta['current_page'] - 2]['url'].'">';
            }

            if ($meta['current_page'] < $meta['last_page']) {
                $html .= '<link rel="next" href="'.$links[$meta['current_page']]['url'].'">';
            }
        }

        // Custom render hook
        if (is_callable($customRender)) {
            $html = call_user_func($customRender, $html, $meta, $links, $options);
        }

        return $html;
    }
}
