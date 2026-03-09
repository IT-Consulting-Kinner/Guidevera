# Services API Reference

## PagesService

Stateless utility class with all methods as `static`. No instantiation needed.

### HTML Sanitization

#### `sanitizeHtml(string $html): string`

Whitelist-based HTML sanitizer using DOMDocument. Removes dangerous tags, event handlers, and malicious URLs while preserving safe formatting.

**Allowed tags**: p, br, hr, div, span, blockquote, pre, code, h1-h6, b, strong, i, em, u, s, sub, sup, small, mark, font, ul, ol, li, table, thead, tbody, tfoot, tr, th, td, caption, a, img, figure, figcaption, details, summary

**Sanitization pipeline**:
1. Strip null bytes
2. Remove dangerous tags with content (script, style, iframe, etc.)
3. Remove `on*` event handlers
4. Neutralize javascript:/data:/vbscript: URIs
5. DOMDocument whitelist filter (tag-by-tag, attribute-by-attribute)
6. CSS property sanitization (expression, behavior, -moz-binding)
7. URL scheme validation
8. Force `rel="noopener noreferrer"` on blank-target links

### Chapter Numbering

#### `calculateChapterNumbering(array $pages, bool $prependTitle = true): array`

Calculates hierarchical chapter numbers based on parent/child relationships.

**Parameters**:
- `$pages` — Flat array of page entities or arrays, ordered by position
- `$prependTitle` — If true, prepends "1.2.3 " to each page title

**Returns**: Same array with `chapter` property added to each page. If `$prependTitle` is true, titles are modified in-place.

**Example**: Given pages Root → Chapter 1 → Section 1.1, the chapter values would be "", "1", "1.1".

#### `buildChapterString(array $pos, int $level): string`

Builds a chapter string like "1.2.3" from the position array and current nesting level.

#### `getChapterForPage(int $pageId, array $numberedPages): string`

Looks up the chapter number for a specific page ID.

### Navigation

#### `calculateNavigation(int $currentId, array $pages): array`

Computes first/previous/next/last page references for sequential navigation.

**Returns**: `['firstId' => int, 'firstTitle' => string, 'previousId' => int, ..., 'nextId' => ..., 'lastId' => ...]`

Only considers active pages. Returns 0 for first/last if current page IS the first/last.

#### `buildNavigationHtml(array $pages, int $activeId, bool $showRoot, bool $showIcons, bool $isAuth, array $openState): string`

Builds the complete navigation tree as an HTML `<li>` list for server-side rendering.

**Behavior differences by auth state**:
- **Guest**: Links use real URLs (`/pages/{id}/{slug}`) for SEO
- **Auth**: Links use `onclick="post_page_show(id)"` for AJAX navigation
- **Guest**: Inactive pages get `class="hidden"` (CSS hides them)
- **Auth**: Inactive pages are visible with `class="inactive"` (red text)

### Utilities

#### `buildTitleLookup(array $pages): array`

Creates an `[id => title]` lookup map from a numbered pages array.

#### `loadKeywords(int $pageId): string`

Loads comma-separated keywords for a page from the pagesindex table.

## PageContentComponent

CakePHP Component loaded by PagesController. Handles read-only operations.

#### `searchPages(string $search, bool $isLoggedIn): array`

Full-text LIKE search across title, description, and content columns.

**Returns**: `['results' => [{id, title, status}], 'search' => 'query']`

#### `buildIndex(bool $isLoggedIn): array`

Aggregates keyword-to-page mappings from the pagesindex table.

**Returns**: `['indexes' => ['keyword' => [{page_id, title, status}]]]`

#### `loadPrintPage(int $id, bool $isLoggedIn): ?array`

Loads a single page with sanitized content for the print view.

#### `loadPrintAll(): array`

Loads all active pages with sanitized content for the complete book print view.
