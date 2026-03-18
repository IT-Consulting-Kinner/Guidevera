# Models API Reference

## Table Classes

All table classes use CakePHP's Timestamp behavior for `created`/`modified` fields.

### PagesTable
- Associations: `belongsTo` CreatedByUsers, ModifiedByUsers. `hasMany` PageRevisions, PageTranslations, PageFeedback, PageComments, PageTags.
- `findChildrenOf(parentId)` — Find non-deleted children of a page.
- Validation: title (max 255), status (in: active, inactive), role validation.

### UsersTable
- Validation: role must be one of 'editor', 'contributor', 'admin'. Username max 20, email max 255.
- Soft delete via `status = 'deleted'`.

### MediaFilesTable
- `belongsTo` Users (uploaded_by), MediaFolders (folder_id).
- Fields: filename, original_name, mime_type, file_size, display_mode, visibility flags, download_count.

### MediaFoldersTable
- Self-referential: `belongsTo` ParentFolders, `hasMany` ChildFolders.
- `hasMany` MediaFiles.
- Unlimited nesting depth.

### Other Tables
- **PageRevisionsTable**: `belongsTo` Pages, CreatedByUsers.
- **PageTranslationsTable**: `belongsTo` Pages. Unique on (page_id, locale).
- **PageFeedbackTable**: `belongsTo` Pages. Rating as tinyint (-1, 0, 1).
- **PageCommentsTable**: `belongsTo` Pages, Users.
- **PageReviewsTable**: `belongsTo` Pages. Reviewer assignment with status.
- **PageSubscriptionsTable**: `belongsTo` Pages, Users.
- **PageAcknowledgementsTable**: `belongsTo` Pages, Users.
- **InlineCommentsTable**: `belongsTo` Pages, Users. Anchor text + resolved flag.
- **WebhooksTable**: URL, secret, active flag.
- **AuditLogTable**: Action log with IP and timestamp.
- **PagesindexTable**: `belongsTo` Pages. Keyword index.
