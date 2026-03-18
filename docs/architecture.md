# Architecture

## Request Flow

```
Browser → Apache (.htaccess rewrite) → webroot/index.php
  → CakePHP Middleware Stack:
    1. ErrorHandlerMiddleware
    2. HostHeaderMiddleware (validates Host header)
    3. CspMiddleware (Content-Security-Policy with nonces)
    4. RoutingMiddleware
    5. BodyParserMiddleware
    6. CsrfProtectionMiddleware
  → Controller Action → JSON Response or Template Render
```

## SPA Hybrid Architecture

Guidevera uses a hybrid approach: the first page load is server-side rendered (SSR) for SEO and fast first paint. Subsequent navigation is handled client-side via JSON APIs.

- **Guest users:** SSR navigation tree, SSR page content including feedback and prev/next links. No JavaScript required for basic reading.
- **Authenticated users:** SSR on first load, then JavaScript takes over. Page tree, content, and all features are loaded via AJAX (`/pages/show`, `/pages/edit`, `/pages/get_tree`).
- **Edit mode:** Summernote WYSIWYG editor with custom plugins (file browser, smart links, media picker).

## Role Hierarchy

```
guest (0) < editor (1) < contributor (2) < admin (3)
```

`hasRole(minRole)` checks `userLevel >= minLevel`.

| Action | Editor | Contributor | Admin |
|--------|--------|-------------|-------|
| View pages | ✓ | ✓ | ✓ |
| Edit/save pages | ✓ | ✓ | ✓ |
| Create/delete pages | — | ✓ | ✓ |
| Set page status (active/inactive) | — | ✓ | ✓ |
| Reorder pages | — | ✓ | ✓ |
| Upload files | ✓ | ✓ | ✓ |
| Delete files / manage folders | — | ✓ | ✓ |
| File settings (visibility, display mode) | — | ✓ | ✓ |
| Revisions (no workflow) | ✓ | ✓ | ✓ |
| Revisions (with workflow) | — | ✓ | ✓ |
| Trash restore | — | ✓ | ✓ |
| Trash purge | — | — | ✓ |
| User management | — | — | ✓ |

Editors with workflow enabled: save sets `workflow_status` to `'review'` automatically.

## Page Status vs. Workflow Status

Every page has two independent status fields:

- **`status`** (`active` / `inactive`) — controls visibility to guests. Toggle requires Contributor+.
- **`workflow_status`** (`draft` / `review` / `published` / `archived`) — editorial state. Only visible in the UI when `enableReviewProcess = true`. Editors can move between draft/review; Contributors/Admins can publish or archive.

When `enableReviewProcess = false`, new pages are created with `workflow_status = 'published'` and the workflow UI is hidden entirely.

## File Management

Files are stored in `storage/media/` as `{id}_{originalname}`. Referenced by ID in URLs: `/downloads/{id}/{originalname}`. This means renaming, moving between folders, or reorganising never breaks existing links in page content.

Each file has:
- `display_mode` (`inline` / `download`) — controls browser behaviour on download
- Per-role visibility flags (`visible_guest`, `visible_editor`, `visible_contributor`, `visible_admin`)
- `download_count` — atomic increment on each access

## Locale Detection

When a user requests a page, the content language is determined by a three-step fallback:
1. Explicit `?locale=` query parameter or POST field
2. Session-stored preference (`userLocale`)
3. Browser `Accept-Language` header

If no match is found, `defaultLocale` from config is used.

## Quality Check

`bin/cake quality-check` analyses all pages (excluding root if `showNavigationRoot = false`) for:
- Missing description
- Stale content (not updated in `staleContentMonths` months)
- Empty content
- Missing keywords
- Missing tags (only when `enableSmartLinks = true`)
- Heading level issues
- Broken internal links
- Missing referenced images

Results are cached and displayed in the Dashboard quality report widget.

## Controllers

- **PagesController** (55+ actions): All page operations, dashboard, search, export, import, workflow, subscriptions, acknowledgements, inline comments, analytics, smart links
- **UsersController**: Login/logout, profile, user CRUD, page tree, user search
- **FilesController**: Upload, download, delete, folders, move, browse, settings
- **RevisionsController**: List, show, restore (with role-aware workflow check)
- **FeedbackController**: Submit (public, rate-limited) and moderate feedback
- **CommentsController**: Page comments with @mentions
- **MediaController**: Media library overview + file replacement

## Services

- **PagesService**: HTML sanitizer (DOMDocument + regex, two-pass), chapter numbering, navigation, breadcrumbs, title lookup, keyword loading, root page helpers (`getRootPageId`, `shouldHideRoot`)
- **UploadService**: File upload with size/type validation and timestamped naming
- **WebhookService**: Async HTTP POST dispatch via file queue (`tmp/webhook_queue/`), SSRF protection, DNS pinning

## Commands

- **InstallCommand**: Creates all 17 tables, admin account, storage directories
- **PublishSchedulerCommand**: Auto-publish pages where `publish_at <= now`, auto-expire where `expire_at <= now`. Run every 5 minutes via cron.
- **QualityCheckCommand**: Reports content quality issues, purges expired trash, caches results for dashboard. Run nightly via cron.
- **WebhookWorkerCommand**: Processes queued webhook events from `tmp/webhook_queue/`
