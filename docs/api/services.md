# Services API Reference

## PagesService

Stateless utility class. All methods are `static`.

### HTML Sanitization

`sanitizeHtml(string $html): string` — Removes dangerous content from user HTML:
- Strips `<script>`, `<iframe>`, `<object>`, `<embed>` tags
- Removes `on*` event handler attributes
- Removes `javascript:` URLs from `href`/`src`
- Adds `rel="noopener noreferrer"` to `target="_blank"` links
- Preserves safe tags: `<p>`, `<a>`, `<img>`, `<table>`, `<h1>`–`<h6>`, `<ul>`, `<ol>`, `<li>`, `<strong>`, `<em>`, `<code>`, `<pre>`, `<blockquote>`, `<hr>`, `<br>`, `<div>`, `<span>`, `<sup>`, `<sub>`

### Chapter Numbering

`calculateChapterNumbering(array $pages, bool $numbered = true): array` — Adds chapter numbers (1, 1.1, 1.2, 2, 2.1) to a flat page array based on parent_id hierarchy.

### Navigation

`calculateNavigation(int $pageId, array $pages): array` — Returns `['previousId' => int, 'nextId' => int]` for prev/next navigation.

`buildNavigationHtml(array $pages, int $selectedId, bool $showIcons, bool $showRoot, bool $isAuth): string` — Generates server-side rendered HTML for the page tree navigation.

### Breadcrumbs

`buildBreadcrumbs(int $pageId, array $pages): array` — Returns ordered array of `['id' => int, 'title' => string]` from root to current page.

### Title Lookup

`buildTitleLookup(array $pages): array` — Returns `[id => title]` map.

### Keywords

`loadKeywords(int $pageId): string` — Returns comma-separated keyword string for a page.

### Cache

`invalidateCache(): void` — Clears page tree cache after any structural change.

## UploadService

Handles file upload validation and storage.

## WebhookService

`fire(string $event, array $data): void` — Dispatches HTTP POST to all active webhooks for the given event. Respects `enableWebhooks` config flag. Events: `page.updated`, `page.created`, `page.deleted`, `review.assigned`.
