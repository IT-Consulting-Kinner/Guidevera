# Database Schema

17 tables, all InnoDB with utf8mb4. Timestamps are `datetime NOT NULL` managed by CakePHP's Timestamp behavior.

## Core Tables

### users

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| id | bigint unsigned | auto | Primary key |
| gender | varchar(10) | '' | 'male'/'female' (avatar icon) |
| username | varchar(20) | '' | Unique login name |
| password | varchar(255) | '' | HMAC-SHA256 + bcrypt hash |
| fullname | varchar(50) | '' | Display name |
| email | varchar(255) | '' | Email (unique among non-deleted) |
| role | varchar(10) | 'editor' | 'editor', 'contributor', or 'admin' |
| change_password | tinyint(1) | 0 | Force password change on next login |
| page_tree | text | — | JSON: sidebar open/closed state |
| notify_mentions | tinyint(1) | 1 | Receive @mention notifications |
| preferences | text | — | JSON user preferences |
| status | varchar(10) | 'inactive' | 'active', 'inactive', 'deleted' |

### pages

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| id | bigint unsigned | auto | Primary key |
| created / modified | datetime | — | Managed by Timestamp behavior |
| created_by / modified_by | bigint unsigned | 0 | User ID references |
| parent_id | bigint unsigned | NULL | Parent page (NULL = root) |
| position | bigint unsigned | 0 | Sort order |
| title | varchar(255) | '' | Page title |
| description | varchar(160) | '' | SEO meta description |
| content | longtext | — | HTML content |
| views | bigint unsigned | 0 | View counter |
| status | varchar(10) | 'inactive' | 'active' / 'inactive' |
| workflow_status | varchar(20) | 'draft' | 'draft', 'review', 'published', 'archived' |
| locale | varchar(10) | 'en' | Primary locale |
| publish_at | timestamp | NULL | Scheduled publish date |
| expire_at | timestamp | NULL | Scheduled expiration date |
| review_due_at | timestamp | NULL | Review reminder date |
| requires_ack | tinyint(1) | 0 | Requires read acknowledgement |
| deleted_at | timestamp | NULL | Soft delete timestamp |

### pagesindex

Keyword-to-page associations for the index sidebar.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| keyword | text | Keyword string |
| page_id | bigint unsigned | Associated page |

## File Management

### media_folders

Unlimited nesting depth via `parent_id`.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| id | bigint unsigned | auto | Primary key |
| parent_id | bigint unsigned | NULL | Parent folder (NULL = root) |
| name | varchar(255) | '' | Folder name |
| created_by | bigint unsigned | 0 | Creator user ID |
| created | datetime | — | Creation timestamp |

### media_files

Files are referenced by ID: `/downloads/{id}/{original_name}`. Moving or renaming never breaks links.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| id | bigint unsigned | auto | Primary key |
| folder_id | bigint unsigned | NULL | Parent folder (NULL = root) |
| filename | varchar(255) | '' | Physical filename: `{id}_{original}` |
| original_name | varchar(255) | '' | Original upload name |
| mime_type | varchar(100) | '' | MIME type |
| file_size | bigint unsigned | 0 | Size in bytes |
| display_mode | varchar(10) | 'download' | 'download' (force save) or 'inline' (show in browser) |
| visible_guest | tinyint(1) | 1 | Visible to guests |
| visible_editor | tinyint(1) | 1 | Visible to editors |
| visible_contributor | tinyint(1) | 1 | Visible to contributors |
| visible_admin | tinyint(1) | 1 | Visible to admins |
| download_count | int unsigned | 0 | Download counter |
| uploaded_by | bigint unsigned | 0 | Uploader user ID |
| created | datetime | — | Upload timestamp |

## Content Features

### page_revisions

Automatic snapshots before each save. Used for history and diff comparison.

### page_translations

Content translations per locale. Tracks `base_modified` to flag stale translations.

### page_feedback

Thumbs up/down ratings with optional comments. IP-based rate limiting.

### page_comments

Internal discussion comments per page. Supports @mentions.

### page_tags

Tag associations per page.

### page_reviews

Review assignments with status (pending/approved/rejected) and comments.

### page_subscriptions

User subscriptions to pages for change notifications.

### page_acknowledgements

Read confirmations per user per page with timestamp.

### inline_comments

Paragraph-level comments with text anchor and resolved status.

### webhooks

Registered webhook URLs with secret and active flag.

### audit_log

Administrative action log (action, entity type, entity ID, details, IP, user, timestamp).

## Entity Relationships

```
users ──< pages.created_by / modified_by
pages ──< pages.parent_id (self-referential tree)
pages ──< pagesindex / page_revisions / page_translations
pages ──< page_feedback / page_comments / page_tags
pages ──< page_reviews / page_subscriptions / page_acknowledgements
pages ──< inline_comments
media_folders ──< media_folders.parent_id (self-referential tree)
media_folders ──< media_files.folder_id
```
