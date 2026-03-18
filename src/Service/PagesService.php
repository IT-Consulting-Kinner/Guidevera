<?php

declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Datasource\FactoryLocator;

/**
 * Pages Service
 *
 * Stateless utility class providing core business logic for pages:
 *
 * - **HTML Sanitization**: Whitelist-based DOMDocument sanitizer that strips
 *   dangerous tags/attributes while preserving safe formatting. This is the
 *   primary XSS defense for user-generated content.
 *
 * - **Chapter Numbering**: Calculates hierarchical chapter numbers (e.g., "1.2.3")
 *   based on the page tree structure and parent/child relationships.
 *
 * - **Navigation**: Computes first/previous/next/last page links for
 *   sequential browsing through the documentation.
 *
 * - **SSR Navigation HTML**: Builds the server-side rendered navigation tree
 *   HTML for the initial page load (SEO-friendly, works without JavaScript).
 *
 * - **Keyword Loading**: Retrieves comma-separated keywords from the
 *   pagesindex table for a given page.
 *
 * All methods are static — no instance state. This class is used by both
 * PagesController and PageContentComponent.
 *
 * ## Sanitizer Design
 *
 * The sanitizer uses a two-pass approach:
 * 1. Regex pre-pass: Removes script/style/iframe tags and event handlers
 * 2. DOMDocument pass: Walks the DOM tree, removing disallowed tags (preserving
 *    their children) and stripping disallowed attributes. Safe URLs are validated
 *    against an allowlist of schemes (http, https, mailto, tel).
 *
 * The tag/attribute whitelist contains 42 allowed tags with specific attribute
 * lists per tag. Dangerous CSS properties (expression, behavior, -moz-binding)
 * are stripped from style attributes.
 *
 * @package App\Service
 */
class PagesService
{
    // ── Whitelist-based HTML sanitizer (DOMDocument-based, port of SanitizeController) ──
    private static array $allowedTags = [
        'p' => ['style', 'class', 'dir'], 'br' => [], 'hr' => [],
        'div' => ['style', 'class', 'dir', 'id'], 'span' => ['style', 'class', 'dir'],
        'blockquote' => ['style', 'class'], 'pre' => ['style', 'class'], 'code' => ['style', 'class'],
        'h1' => ['style', 'class'], 'h2' => ['style', 'class'], 'h3' => ['style', 'class'],
        'h4' => ['style', 'class'], 'h5' => ['style', 'class'], 'h6' => ['style', 'class'],
        'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
        'small' => [],
        'font' => ['color', 'size', 'face'],
        'ul' => ['style', 'class'], 'ol' => ['style', 'class', 'start', 'type'], 'li' => ['style', 'class'],
        'table' => ['style', 'class', 'border', 'cellpadding', 'cellspacing', 'width'],
        'thead' => [], 'tbody' => [], 'tfoot' => [],
        'tr' => ['style', 'class'],
        'th' => ['style', 'class', 'colspan', 'rowspan', 'width', 'align', 'valign'],
        'td' => ['style', 'class', 'colspan', 'rowspan', 'width', 'align', 'valign'],
        'caption' => ['style', 'class'],
        'a' => ['href', 'title', 'target', 'rel', 'class'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'style', 'class'],
        'figure' => ['style', 'class'], 'figcaption' => ['style', 'class'],
        'details' => ['open'], 'summary' => [],
    ];

    private static array $dangerousCssProperties = [
        'behavior', 'expression', '-moz-binding', 'binding',
    ];

    private static array $allowedSchemes = ['http', 'https', 'mailto', 'tel', '//'];

    public static function sanitizeHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove null bytes
        $html = str_replace("\0", '', $html);

        // Remove dangerous tags and their content (multi-pass to catch nested tags)
        $dangerousTags = 'script|style|iframe|object|embed|form|input'
            . '|textarea|select|button|applet|meta|link|base'
            . '|svg|math|noscript|template|xmp|noembed';
        $prevHtml = '';
        $maxPasses = 5;
        while ($prevHtml !== $html && $maxPasses-- > 0) {
            $prevHtml = $html;
            $html = preg_replace(
                '/<(' . $dangerousTags . ')[^>]*>.*?<\/\1>/si',
                '',
                $html
            );
            $html = preg_replace(
                '/<(' . $dangerousTags . ')[^>]*\/?>/si',
                '',
                $html
            );
        }

        // Remove event handlers
        $html = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/si', '', $html);

        // Remove javascript:/data:/vbscript: URIs
        $html = preg_replace_callback(
            '/(href|src|action|formaction|xlink:href)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/si',
            function ($matches) {
                $attr = $matches[1];
                $url = $matches[2] ?? $matches[3] ?? '';
                $urlClean = trim(preg_replace('/[\x00-\x20]+/', '', $url));
                if (preg_match('/^(javascript|data|vbscript)\s*:/i', strtolower($urlClean))) {
                    return $attr . '="#"';
                }
                return $matches[0];
            },
            $html
        );

        // Use DOMDocument for proper tag/attribute filtering
        return self::filterTagsAndAttributes($html);
    }

    private static function filterTagsAndAttributes(string $html): string
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div id="sanitize-wrapper">' . $html . '</div>';
        $doc->loadHTML('<?xml encoding="UTF-8"><body>' .
            $wrapped . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        self::processNode($doc->documentElement);

        $wrapper = null;
        $elements = $doc->getElementsByTagName('div');
        foreach ($elements as $elem) {
            if ($elem->getAttribute('id') === 'sanitize-wrapper') {
                $wrapper = $elem;
                break;
            }
        }
        if (!$wrapper) {
            return '';
        }

        $output = '';
        foreach ($wrapper->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }
        return $output;
    }

    private static function processNode(\DOMNode $node): void
    {
        if (!$node->hasChildNodes()) {
            return;
        }

        $nodes = [];
        foreach ($node->childNodes as $child) {
            $nodes[] = $child;
        }

        foreach ($nodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);

                if (!array_key_exists($tagName, self::$allowedTags)) {
                    // Replace disallowed tag with its children (preserve content)
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                // Remove disallowed attributes
                $allowedAttrs = self::$allowedTags[$tagName];
                $removeAttrs = [];
                foreach ($child->attributes as $attr) {
                    if (!in_array(strtolower($attr->name), $allowedAttrs)) {
                        $removeAttrs[] = $attr->name;
                    }
                }
                foreach ($removeAttrs as $attrName) {
                    $child->removeAttribute($attrName);
                }

                // Sanitize style attribute
                if ($child->hasAttribute('style')) {
                    $style = self::sanitizeCss($child->getAttribute('style'));
                    if (empty($style)) {
                        $child->removeAttribute('style');
                    } else {
                        $child->setAttribute('style', $style);
                    }
                }

                // Sanitize href/src attributes
                foreach (['href', 'src'] as $urlAttr) {
                    if ($child->hasAttribute($urlAttr)) {
                        if (!self::isSafeUrl($child->getAttribute($urlAttr))) {
                            $child->setAttribute($urlAttr, '#');
                        }
                    }
                }

                // Force rel="noopener noreferrer" on external links
                if ($tagName === 'a' && $child->hasAttribute('target') && $child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }

                self::processNode($child);
            }
        }
    }

    private static function sanitizeCss(string $css): string
    {
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        // Decode CSS escape sequences (e.g. \6A -> j) before checking
        $css = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?/', function ($m) {
            return mb_chr((int)hexdec($m[1]), 'UTF-8');
        }, $css);
        // Decode HTML entities
        $css = html_entity_decode($css, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach (self::$dangerousCssProperties as $prop) {
            $css = preg_replace('/\b' . preg_quote($prop, '/') . '\b\s*:/i', 'blocked:', $css);
        }
        $css = preg_replace('/expression\s*\(/i', 'blocked(', $css);
        // Block @import rules (can load external stylesheets)
        $css = preg_replace('/@import\b[^;]*(;|$)/i', '', $css);
        // Block all url() calls except relative paths (no external references)
        $css = preg_replace_callback('/url\s*\(\s*["\']?\s*([^)"\']*)[\s"\']*\)/i', function ($m) {
            $target = trim($m[1]);
            $targetClean = strtolower(preg_replace('/[\x00-\x20]+/', '', $target));
            // Only allow relative paths, block all absolute URLs (including http(s) for privacy)
            if (!preg_match('/^[a-z]+:/i', $targetClean) && !str_starts_with($targetClean, '//')) {
                return $m[0]; // Safe: relative path only
            }
            return 'url(blocked:)';
        }, $css);
        $css = preg_replace('/blocked\s*:[^;]*(;|$)/i', '', $css);
        return trim($css);
    }

    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if (empty($url) || $url === '#') {
            return true;
        }
        $urlCheck = strtolower(preg_replace('/[\x00-\x20]+/', '', urldecode($url)));
        if (preg_match('/^(javascript|data|vbscript)\s*:/i', $urlCheck)) {
            return false;
        }
        if ($url[0] === '/' || $url[0] === '#' || $url[0] === '?') {
            return true;
        }
        foreach (self::$allowedSchemes as $scheme) {
            if (strpos($urlCheck, $scheme) === 0) {
                return true;
            }
        }
        if (!preg_match('/^[a-z]+:/i', $url)) {
            return true;
        }
        return false;
    }

    /**
     * Strip external resource URLs for safe PDF generation (SSRF mitigation).
     * Replaces external src/href attributes with safe placeholders.
     */
    public static function stripExternalResources(string $html): string
    {
        // Replace external img src with placeholder
        $html = preg_replace_callback(
            '/<img([^>]*)\bsrc\s*=\s*(["\'])(https?:\/\/[^"\']*)\2/si',
            function ($m) {
                return '<img' . $m[1] . 'src=' . $m[2] . '#external-removed' . $m[2];
            },
            $html
        );
        // Remove link/script/iframe with external sources (shouldn't exist after sanitizer, but defense in depth)
        $html = preg_replace('/<(link|script|iframe|object|embed|frame)\b[^>]*>/si', '', $html);
        return $html;
    }

    // ── Chapter numbering ──
    /**
     * Get chapter-numbered pages with caching.
     * Cache is invalidated when tree structure changes (create/delete/reorder).
     */
    public static function getNumberedPages(bool $showNumbering = true): array
    {
        $cacheKey = 'chapter_numbering_' . ($showNumbering ? '1' : '0');
        $cached = \Cake\Cache\Cache::read($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $pages = FactoryLocator::get('Table')->get('Pages')
            ->find()->where(['deleted_at IS' => null])->orderBy(['position' => 'ASC'])->all()->toArray();
        $numbered = self::calculateChapterNumbering($pages, $showNumbering);

        \Cake\Cache\Cache::write($cacheKey, $numbered);
        return $numbered;
    }

    /**
     * Invalidate the chapter numbering cache.
     * Call after any tree mutation: create, delete, reorder, status change.
     */
    public static function invalidateCache(): void
    {
        \Cake\Cache\Cache::delete('chapter_numbering_1');
        \Cake\Cache\Cache::delete('chapter_numbering_0');
    }

    public static function calculateChapterNumbering(array $pages, bool $prependTitle = true): array
    {
        $parentLevelPos = [];
        $parentLevel = 0;
        $previousParent = 0;
        $parentStack = [];
        foreach ($pages as $key => $page) {
            $parentId = (int)($page['parent_id'] ?? (is_object($page) ? $page->parent_id : 0) ?? 0);
            $status = $page['status'] ?? (is_object($page) ? $page->status : 'inactive');
            $title = $page['title'] ?? (is_object($page) ? $page->title : '');

            if ($parentId > $previousParent) {
                $parentLevel++;
                $parentLevelPos[$parentLevel] = 1;
                array_push($parentStack, $previousParent);
            } elseif ($parentId < $previousParent) {
                while (!empty($parentStack) && array_pop($parentStack) != $parentId) {
                    $parentLevel--;
                    $parentLevelPos[$parentLevel] = ($parentLevelPos[$parentLevel] ?? 0) + 1;
                }
                $parentLevel--;
                $parentLevelPos[$parentLevel] = ($parentLevelPos[$parentLevel] ?? 0) + 1;
            } else {
                if ($status === 'active') {
                    $parentLevelPos[$parentLevel] = ($parentLevelPos[$parentLevel] ?? 0) + 1;
                }
            }
            $previousParent = $parentId;
            $chapter = '';
            if ($status === 'active') {
                $chapter = self::buildChapterString($parentLevelPos, $parentLevel);
            }

            if (is_array($page)) {
                $pages[$key]['chapter'] = $chapter;
                if ($prependTitle && strlen($chapter)) {
                    $pages[$key]['title'] = $chapter . ' ' . $title;
                }
            } else {
                $page->chapter = $chapter;
                if ($prependTitle && strlen($chapter)) {
                    $page->title = $chapter . ' ' . $title;
                }
            }
        }
        return $pages;
    }

    public static function buildChapterString(array $pos, int $level): string
    {
        $ch = '';
        for ($i = 0; $i <= $level; $i++) {
            if ($i > 0) {
                $ch .= ($pos[$i] ?? 0);
            }
            if ($i > 0 && $i < $level) {
                $ch .= '.';
            }
        }
        return $ch;
    }

    /**
     * Build breadcrumb trail for a page: [root, ..., parent, current].
     */
    public static function buildBreadcrumbs(int $pageId, array $pages): array
    {
        $byId = [];
        foreach ($pages as $p) {
            $id = is_object($p) ? $p->id : ($p['id'] ?? 0);
            $byId[$id] = $p;
        }
        $crumbs = [];
        $current = $byId[$pageId] ?? null;
        $maxDepth = 50;
        while ($current) {
            if (--$maxDepth <= 0) {
                break;
            }
            $id = is_object($current) ? $current->id : ($current['id'] ?? 0);
            $title = is_object($current) ? ($current->title ?? '') : ($current['title'] ?? '');
            array_unshift($crumbs, ['id' => $id, 'title' => $title]);
            $parentId = (int)(is_object($current) ? ($current->parent_id ?? 0) : ($current['parent_id'] ?? 0));
            $current = $parentId ? ($byId[$parentId] ?? null) : null;
        }
        return $crumbs;
    }

    public static function buildTitleLookup(array $pages): array
    {
        $lookup = [];
        foreach ($pages as $p) {
            $id = is_array($p) ? ($p['id'] ?? 0) : ($p->id ?? 0);
            $title = is_array($p) ? ($p['title'] ?? '') : ($p->title ?? '');
            $lookup[$id] = $title;
        }
        return $lookup;
    }

    // ── Navigation ──
    public static function calculateNavigation(int $currentId, array $pages, bool $showRoot = true): array
    {
        $nav = ['firstId' => 0, 'firstTitle' => '', 'previousId' => 0, 'previousTitle' => '', 'nextId' => 0,
            'nextTitle' => '', 'lastId' => 0, 'lastTitle' => ''];
        // Determine root page ID (first in tree)
        $rootId = 0;
        if (!$showRoot && !empty($pages)) {
            $first = $pages[0];
            $rootId = (int)(is_array($first) ? ($first['id'] ?? 0) : ($first->id ?? 0));
        }
        $active = [];
        foreach ($pages as $p) {
            $s = is_array($p) ? $p['status'] : $p->status;
            $pid = (int)(is_array($p) ? $p['id'] : $p->id);
            if ($s !== 'active') {
                continue;
            }
            if ($rootId && $pid === $rootId) {
                continue;
            }
            $active[] = $p;
        }
        if (empty($active)) {
            return $nav;
        }
        $ci = -1;
        foreach ($active as $i => $p) {
            if ((is_array($p) ? $p['id'] : $p->id) == $currentId) {
                $ci = $i;
                break;
            }
        }
        $f = $active[0];
        $l = $active[count($active) - 1];
        $nav['firstId'] = is_array($f) ? $f['id'] : $f->id;
        $nav['firstTitle'] = is_array($f) ? $f['title'] : $f->title;
        $nav['lastId'] = is_array($l) ? $l['id'] : $l->id;
        $nav['lastTitle'] = is_array($l) ? $l['title'] : $l->title;
        if ($ci > 0) {
            $p = $active[$ci - 1];
            $nav['previousId'] = is_array($p) ? $p['id'] : $p->id;
            $nav['previousTitle'] = is_array($p) ? $p['title'] : $p->title;
        }
        if ($ci >= 0 && $ci < count($active) - 1) {
            $p = $active[$ci + 1];
            $nav['nextId'] = is_array($p) ? $p['id'] : $p->id;
            $nav['nextTitle'] = is_array($p) ? $p['title'] : $p->title;
        }
        if ($nav['firstId'] == $currentId) {
            $nav['firstId'] = 0;
        }
        if ($nav['lastId'] == $currentId) {
            $nav['lastId'] = 0;
        }
        return $nav;
    }

    public static function loadKeywords(int $pageId): string
    {
        $table = FactoryLocator::get('Table')->get('Pagesindex');
        return implode(', ', $table->find()->where(['page_id' => $pageId])->all()->extract('keyword')->toArray());
    }

    // ── SSR Navigation HTML ──
    public static function buildNavigationHtml(
        array $pages,
        int $activeId = 0,
        bool $showRoot = true,
        bool $showIcons = true,
        bool $isAuth = false,
        array $openState = []
    ): string {
        if (empty($pages)) {
            return '';
        }
        $children = [];
        $rootId = 0;
        foreach ($pages as $i => $p) {
            $id = is_object($p) ? $p->id : ($p['id'] ?? 0);
            $pid = (int)(is_object($p) ? ($p->parent_id ?? 0) : ($p['parent_id'] ?? 0));
            if ($i === 0) {
                $rootId = $id;
            }
            $children[$pid][] = $p;
        }
        $hasOpen = !empty($openState);

        // Helper: build <a> tag — real href for guests, data-action for auth
        $makeLink = function (int $id, string $title, string $classes, string $rawTitle) use ($isAuth): string {
            if ($isAuth) {
                return '<a name="a_' .
                    $id . '" href="#" data-action="postPageShow" data-arg="' . $id . '" class="' . $classes .
                        '">' . $title . '</a>';
            }
            $slug = preg_replace('/\s+/', '-', preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $rawTitle));
            return '<a name="a_' .
                $id . '" href="/pages/' . $id . '/' . rawurlencode($slug) . '" class="' . $classes . '
                    ">' . $title . '</a>';
        };

        $render = function (int $parentId) use (
            &$render,
            &$children,
            $activeId,
            $showRoot,
            $showIcons,
            $isAuth,
            $openState,
            $hasOpen,
            $rootId,
            $makeLink
): string {
            if (!isset($children[$parentId])) {
                return '';
            }
            $html = '';
            foreach ($children[$parentId] as $p) {
                $id = is_object($p) ? $p->id : ($p['id'] ?? 0);
                $rawTitle = is_object($p) ? ($p->title ?? '') : ($p['title'] ?? '');
                $title = htmlspecialchars($rawTitle, ENT_QUOTES, 'UTF-8');
                $status = is_object($p) ? ($p->status ?? 'inactive') : ($p['status'] ?? 'inactive');
                $hasKids = isset($children[$id]);
                $isRoot = ($id === $rootId);

                if (!$isAuth && $status === 'inactive' && !$isRoot) {
                    $html .= '<li id="list_' .
                        $id . '" class="hidden"><div class="hasmenu p-1"><span class="pe-2"></span>' .
                            $makeLink($id, $title, 'inactive', $rawTitle) . '</div>';
                    if ($hasKids) {
                        $ch = $render($id);
                        if ($ch) {
                            $html .= '<ul>' . $ch . '</ul>';
                        }
                    }
                    $html .= '</li>';
                    continue;
                }

                $isOpen = true;
                if ($hasOpen && $hasKids) {
                    $isOpen = isset($openState[$id]) && $openState[$id];
                }
                $collapsed = (!$isOpen && $hasKids) ? ' mjs-nestedSortable-collapsed' : '';
                $inactCls = $status === 'inactive' ? ' inactive' : '';
                $selCls = ($id === $activeId) ? ' selected' : '';

                if ($isRoot) {
                    $rs = !$showRoot ? ' style="list-style:none"' : '';
                    $rds = !$showRoot ? ' style="display:none"' : '';
                    $html .= '<li id="list_' . $id . '"' . $rs . '><div class="hasmenu p-1"' . $rds . '>';
                    $html .= $showIcons ? '<span class="pe-2 fas fa-book" style="color:darkslategrey"
                        data-icon="book"></span>' : '<span class="pe-2"></span>';
                    $html .= $makeLink($id, $title, $inactCls . $selCls, $rawTitle) . '</div>';
                    if ($hasKids) {
                        $ch = $render($id);
                        if ($ch) {
                            $us = !$showRoot ? ' style="margin:0"' : '';
                            $html .= '<ul' . $us . ' class="' . $collapsed . '">' . $ch . '</ul>';
                        }
                    }
                    $html .= '</li>';
                } else {
                    $html .= '<li id="list_' . $id . '"><div class="hasmenu p-1">';
                    if ($hasKids) {
                        $fi = $isOpen ? 'fa-folder-open' : 'fa-folder';
                        $di = $isOpen ? 'folder-open' : 'folder-closed';
                        $html .= $showIcons ? '<span class="pe-2 fas ' .
                            $fi . '" style="color:#ffb449" data-action="treeView" data-icon="' . $di . '
                                "></span>' : '<span class="pe-2" data-action="treeView" data-icon="' . $di . '
                                    "></span>';
                    } else {
                        $html .= $showIcons ? '<span class="pe-2 far fa-file-alt" style="color:#222"
                            data-icon="document"></span>' : '<span class="pe-2" data-icon="document"></span>';
                    }
                    $html .= $makeLink($id, $title, $inactCls . $selCls, $rawTitle) . '</div>';
                    if ($hasKids) {
                        $ch = $render($id);
                        if ($ch) {
                            $html .= '<ul class="' . $collapsed . '">' . $ch . '</ul>';
                        }
                    }
                    $html .= '</li>';
                }
            }
            return $html;
        };

        $treeRoot = (int)(is_object($pages[0]) ? ($pages[0]->parent_id ?? 0) : ($pages[0]['parent_id'] ?? 0));
        return $render($treeRoot);
    }

    /**
     * Get the root page ID (first page in the ordered tree).
     */
    public static function getRootPageId(array $pages): int
    {
        if (empty($pages)) {
            return 0;
        }
        $first = $pages[0];
        return (int)(is_array($first) ? ($first['id'] ?? 0) : ($first->id ?? 0));
    }

    /**
     * Check if root page should be hidden from public-facing views.
     */
    public static function shouldHideRoot(): bool
    {
        return !(Configure::read('Manual.showNavigationRoot') ?? true);
    }
}
