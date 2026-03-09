# Controllers API Reference

## AppController

Base controller for all application controllers. Provides:

- `isLoggedIn(): bool` — Check session authentication
- `hasRole(string $minRole): bool` — Check minimum role level
- `currentUser(): array` — Get authenticated user data
- `jsonSuccess(array $data): Response` — Return JSON success response
- `jsonError(string $error): Response` — Return JSON error response
- `requireAuth(): bool` — Guard that redirects or returns JSON error
- `requireRole(string $role): bool` — Guard with proper error response for AJAX/non-AJAX

## PagesController

### SSR Endpoints

| Route | Action | Description |
|-------|--------|-------------|
| `GET /pages` | `index()` | Main page with SSR navigation |
| `GET /pages/{id}/{slug}` | `index($id)` | Specific page (SEO-friendly URL) |
| `GET /pages/sitemap` | `sitemap()` | XML sitemap |
| `GET /pages/{id}/print/{title}` | `printPage($id)` | Single page print view |
| `GET /pages/print_all` | `printAll()` | Complete book print view |

### JSON API Endpoints

| Route | Action | Auth | Request | Response |
|-------|--------|------|---------|----------|
| `POST /pages/get_tree` | `getTree()` | No | — | `{arrTree: [{id, parent_id, title, status, views, chapter}]}` |
| `POST /pages/show` | `show()` | No | `{id}` | `{id, title, status, content, keywords, created, createdBy, modified, modifiedBy}` |
| `POST /pages/edit` | `edit()` | Yes | `{id}` | `{id, title, description, keywords, content, status, parentId, created, createdBy, modified, modifiedBy}` |
| `POST /pages/create` | `create()` | Yes | — | `{intId}` |
| `POST /pages/save` | `save()` | Yes | `{id, title, description, content, keywords}` | `{intAffectedRows}` |
| `POST /pages/delete` | `delete()` | Yes | `{id}` | `{intAffectedRows}` |
| `POST /pages/set_status` | `setStatus()` | Yes | `{id, status}` | `{intAffectedRows}` |
| `POST /pages/update_order` | `updateOrder()` | Yes | `{strPages}` | `{intAffectedRows}` |
| `POST /pages/update_parent` | `updateParent()` | Yes | `{id, parent_id}` | `{intAffectedRows}` |
| `POST /pages/browse` | `browse()` | Yes | — | `{pages: [{id, title}]}` |
| `POST /pages/search` | `search()` | No | `{search}` | `{results: [{id, title, status}], search}` |
| `GET /pages/index` | `buildIndex()` | No | — | `{indexes: {keyword: [{page_id, title, status}]}}` |

### Error Responses

All error responses have the format `{"error": "<error_code>"}`:

- `not_authenticated` — User is not logged in
- `invalid_id` — Missing or zero page ID
- `page_not_found` — Page does not exist
- `can_not_read_item` — Failed to load page for editing
- `can_not_create_item` — Failed to create new page
- `can_not_save_item` — Failed to save page
- `can_not_delete_item` — Failed to delete page
- `has_child` — Cannot delete page with child pages
- `invalid_status` — Status must be 'active' or 'inactive'

## UsersController

| Route | Action | Auth | Description |
|-------|--------|------|-------------|
| `GET /user/login` | `login()` | No | Login form (shows setup instructions when no users exist) |
| `POST /user/login` | `login()` | No | Authenticate user |
| `GET /user/logout` | `logout()` | No | Destroy session |
| `GET /user` | `index()` | Admin | User management page |
| `GET /user/profil` | `profil()` | Yes | Profile editing form |
| `GET /user/change-password` | `changePassword()` | Yes | Password change form |
| `GET /user/create` | `create()` | Admin | New user form |
| `POST /user/save` | `save()` | Admin | AJAX: Update single user field |
| `POST /user/delete_user` | `deleteUser()` | Admin | AJAX: Soft-delete user |
| `POST /user/save_page_tree` | `savePageTree()` | Yes | AJAX: Save tree open/closed state |

## FilesController

| Route | Action | Auth | Description |
|-------|--------|------|-------------|
| `GET /file` | `index()` | Admin | File management page |
| `POST /file/upload` | `upload()` | Admin | Upload file |
| `POST /file/delete` | `delete()` | Admin | Delete file |
| `GET /downloads/{filename}` | `download()` | No | Serve file for download |
| `POST /file/browse` | `browse()` | Yes | List files for link picker |
