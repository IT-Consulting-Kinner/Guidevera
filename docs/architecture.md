# Architecture

## Request Flow

```
Browser Request
    │
    ├── Static Assets (webroot/) → Apache/Nginx serves directly
    │
    └── Dynamic Request → CakePHP Router
            │
            ├── HostHeaderMiddleware (validates Host header in production)
            ├── CsrfProtectionMiddleware (validates CSRF tokens)
            ├── RoutingMiddleware (maps URL to controller/action)
            │
            └── Controller Action
                    │
                    ├── AppController::beforeFilter()
                    │   ├── Read session auth → $auth
                    │   ├── Read Manual config → $public
                    │   └── Extract CSRF token → $csrfToken
                    │
                    ├── SSR (GET /pages, /pages/{id})
                    │   └── Renders full HTML with navigation tree
                    │
                    └── JSON API (POST /pages/show, /pages/edit, ...)
                        └── Returns JSON → Client renders HTML
```

## Data Flow: Page Display

### Guest (First Visit)

1. `GET /pages` → `PagesController::index()`
2. Server loads all pages, builds SSR navigation HTML
3. Renders full page with content and navigation tree
4. Navigation links are real URLs (`/pages/{id}/{slug}`)
5. `pages.js` loads, reads `window.pageConfig.ssrTree`
6. JS hydrates the tree (makes it interactive) without AJAX

### Authenticated User (AJAX Navigation)

1. `GET /pages` → Same SSR render as guest
2. `pages.js` detects `isAuth=true`, replaces SSR links with AJAX handlers
3. User clicks page → `POST /pages/show {id: 42}` → JSON response
4. `renderShowView(data)` builds HTML from JSON, injects into `#page`
5. Tree highlight updates, folders expand to show current page

## Data Flow: Page Editing

```
User clicks Edit
    │
    ├── editPage(id) sets state.mode = 'edit'
    ├── POST /pages/edit {id} → JSON with raw page data
    ├── renderEditView(data) builds form + Summernote editor
    │
    ├── User edits content in Summernote WYSIWYG
    │
    ├── User clicks Save
    │   ├── Summernote content → hidden textarea
    │   ├── jQuery('#pageform').serializeArray()
    │   ├── POST /pages/save {id, title, description, content, keywords}
    │   └── Server saves page + updates keyword index
    │
    └── User clicks Close
        ├── Check state.hasChanges → confirm dialog if unsaved
        └── showPage(currentId) → back to read-only view
```

## Tree Structure

Pages use a flat table with `parent_id` and `position` columns:

```
id | parent_id | position | title
---|-----------|----------|------
1  | NULL      | 0        | Root
2  | 1         | 1        | Chapter 1
3  | 1         | 2        | Chapter 2
4  | 2         | 3        | Section 1.1
5  | 2         | 4        | Section 1.2
```

- `parent_id = NULL` → root-level page
- `position` → global sort order (0-based, set by drag-and-drop)
- No `lft`/`rght` columns — no TreeBehavior dependency

## Component Responsibilities

| Component              | Responsibility                              |
|------------------------|---------------------------------------------|
| `PagesController`      | Page CRUD, tree operations, JSON APIs       |
| `PageContentComponent` | Search, keyword index, print views          |
| `PagesService`         | HTML sanitization, chapter numbering, SSR navigation |
| `UsersController`      | Login, logout, profile, user admin          |
| `FilesController`      | File upload, download, browse               |
| `AppController`        | Auth helpers, JSON helpers, config injection |
| `pages.js`             | Client-side rendering, tree management, editor |
| `app.css`              | All custom styles (single consolidated file) |
