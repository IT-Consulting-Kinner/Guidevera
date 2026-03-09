# Database Schema

## Tables

### users

Stores user accounts for authentication and access control.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint unsigned | No | auto | Primary key |
| `gender` | varchar(10) | No | '' | 'male' or 'female' (used for avatar icon) |
| `username` | varchar(20) | No | '' | Unique login name |
| `password` | tinytext | No | — | HMAC-SHA256 + bcrypt hash |
| `fullname` | varchar(50) | No | '' | Display name |
| `email` | varchar(255) | No | '' | Email address (unique among non-deleted) |
| `role` | varchar(10) | No | 'user' | 'admin' or 'user' |
| `change_password` | tinyint(1) | No | 0 | 1 = must change on next login |
| `page_tree` | text | No | — | JSON: sidebar tree open/closed state |
| `status` | varchar(10) | No | 'inactive' | 'active', 'inactive', or 'deleted' |

**Indexes**: PRIMARY (id), UNIQUE (username)

**Soft delete**: Users are never physically deleted. Status is set to 'deleted' to preserve referential integrity with `created_by`/`modified_by` in the pages table.

### pages

Stores documentation pages in a hierarchical tree structure.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint unsigned | No | auto | Primary key |
| `created` | timestamp | No | CURRENT_TIMESTAMP | Creation timestamp |
| `created_by` | bigint unsigned | No | 0 | User ID of creator |
| `modified` | timestamp | No | CURRENT_TIMESTAMP | Last modification timestamp |
| `modified_by` | bigint unsigned | No | 0 | User ID of last editor |
| `parent_id` | bigint unsigned | Yes | NULL | Parent page ID (NULL = root level) |
| `position` | bigint unsigned | No | 0 | Global sort order (0-based) |
| `title` | varchar(255) | No | '' | Page title |
| `description` | varchar(160) | No | '' | Meta description for search engines |
| `content` | longtext | No | — | HTML content (sanitized on display) |
| `views` | bigint unsigned | No | 0 | View counter |
| `status` | varchar(10) | No | 'inactive' | 'active' or 'inactive' |

**Indexes**: PRIMARY (id), KEY (parent_id), KEY (position), KEY (status)

**Tree structure**: Uses `parent_id` + `position` for ordering. No `lft`/`rght` columns (no TreeBehavior). The `position` column defines global sort order — drag-and-drop updates both `position` and `parent_id`.

### pagesindex

Stores keyword-to-page associations for the keyword index sidebar feature.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint unsigned | No | auto | Primary key |
| `keyword` | text | No | — | The keyword string |
| `page_id` | bigint unsigned | No | 0 | Associated page ID |

**Indexes**: PRIMARY (id), KEY (page_id)

**Management**: Keywords are managed by `PagesController::_saveKeywords()` which does a full delete + re-insert on each page save. The comma-separated keyword string from the editor is split and stored as individual rows.

## Entity Relationship Diagram

```
users
  │
  ├──< pages.created_by    (who created the page)
  ├──< pages.modified_by   (who last edited the page)
  │
pages
  │
  ├──< pages.parent_id     (self-referential: parent/child tree)
  ├──< pagesindex.page_id  (keywords for this page)
  │
pagesindex
```
