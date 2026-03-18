# Controllers API Reference

## AppController

Base controller providing authentication helpers, JSON response methods, role checks, and audit logging.

Key methods: `isLoggedIn()`, `hasRole(minRole)`, `requireRole(minRole)`, `currentUser()`, `jsonSuccess(data)`, `jsonError(code)`, `audit(action, entity, id, details)`, `sendNotification(subject, body)`, `sendUserNotification(email, subject, body)`.

Sets `Cache-Control: no-cache` headers and provides `$auth` and `$public` view variables globally.

## PagesController (55+ actions)

### Page CRUD
- `index(?id)` — Main page with SSR. Passes page data, tree, breadcrumbs, feedback, prev/next to view. Respects `showNavigationRoot`.
- `show()` — JSON: load page content, feedback, breadcrumbs, navigation. View counter only increments for guests.
- `edit()` — JSON: load page for editing (includes publish_at, expire_at, workflow_status, locale, available locales).
- `create()` — Create new page (inactive). `workflow_status` = `draft` if review process enabled, else `published`.
- `save()` — Save page content. Editors with workflow: auto-sets `workflow_status='review'`. Saves publish_at/expire_at for contributor+. Creates revision if enabled.
- `saveContentSilent()` — Background auto-save (no revision, no modified update).
- `delete()` — Soft delete with recursive cascade (all descendants). Returns count of affected rows.
- `setStatus()` — Toggle active/inactive (contributor+). Guards against invalid values.

### Tree Operations
- `getTree()` — JSON: full page tree for navigation.
- `updateOrder()` — Save drag-and-drop reordering. Reinitialises sortable after move.
- `updateParent()` — Move page to different parent. Circular reference protection.

### Search & Index
- `search()` — Fulltext search with keyword highlighting. Filters: status, since, workflow.
- `buildIndex()` — Rebuild keyword index.
- `linkSuggest()` — Smart link autocomplete (active pages by title, min 2 chars).

### Dashboard
- `dashboard()` — Recently edited, created, my drafts, review queue, trash count, quality metrics, search misses, page overview table.

### Trash
- `trash()` — List soft-deleted pages (last 100, contributor+).
- `trashRestore()` — Restore page as inactive. Moves to root if parent was also deleted.
- `trashPurge()` — Permanently delete with full data cleanup across 10 related tables (admin only).

### Export / Import
- `exportMarkdown()` — HTML-to-Markdown conversion, downloaded as `.md` file (editor+).
- `exportPdf()` — Server-side PDF with external resource stripping (editor+).
- `import()` — Import HTML or Markdown file as new page, max 2 MB (contributor+).

### Features
- `subscribe()` / `subscriptionStatus()` — Page change subscriptions (toggle).
- `acknowledge()` / `ackStatus()` / `ackReport()` — Locale-aware read acknowledgements. Validity check against page modified date.
- `inlineComments()` / `addInlineComment()` / `resolveInlineComment()` — Paragraph-level review comments.
- `assignReviewer()` / `reviewDecision()` / `pageReviews()` — Review workflow with atomic state transitions and race condition protection.
- `setWorkflowStatus()` — Manual workflow status change (editors: draft/review only; contributors+: all).
- `analytics()` — Content analytics: top/least viewed, bad feedback, frequently updated (admin+, requires enableContentAnalytics).
- `stats()` — Basic statistics: counts, top viewed, recent activity, feedback totals (admin+).
- `auditLog()` — Last 200 audit entries (admin+, requires enableAuditLog).
- `qualityReport()` — Cached quality check results from last cron run (admin+).
- `staleList()` — Pages not updated in `staleContentMonths` months (editor+).
- `translationStatus()` — Pages with missing or stale translations (requires enableTranslations).
- `tags()` / `saveTags()` — Page tag management (lowercase, deduplicated, max 100 chars).
- `relatedPages()` — Pages sharing tags, ordered by shared tag count (raw SQL for reliability).
- `uploadMedia()` — Image upload from Summernote editor. Registered in DB before file move.
- `browse()` — Page list for link picker.
- `sitemap()` — XML sitemap (active pages only, respects showNavigationRoot).
- `printPage()` / `printAll()` — Print-optimised views.

## UsersController

- `login()` — Session-based login with rate limiting (5 attempts / 15 min). Redirects to dashboard or target page.
- `logout()` — Destroy session, redirect to root.
- `profil()` — Edit own profile (name, email, gender, notification preferences). Updates session immediately.
- `changePassword()` — Change own password with strength validation. Redirects after first-login change.
- `create()` — Create user (admin only). Sets `change_password = 1` so user must change on first login.
- `save()` — Update user field inline (admin only). Prevents admin from changing own role or status.
- `deleteUser()` — Soft delete user (`status = 'deleted'`). Cannot delete self.
- `savePageTree()` — Save sidebar open/closed state to user record.
- `searchUsers()` — Username/fullname search for @mention autocomplete. Empty query returns all active users.

## FilesController

ID-based file management with nested folder structure. Files stored as `{id}_{originalname}`.

- `index()` — File management page (HTML). Supports browse mode for Summernote picker.
- `listFiles()` — JSON: files + folders for a folder, with usage tracking (page references), download counts, visibility flags.
- `upload()` — Upload file to folder. MIME detected server-side. Name conflict check. Stores as `{id}_{original}`.
- `delete()` — Delete file (contributor+). Blocked if file is referenced in any page content.
- `download()` — Serve file by ID. Checks per-role visibility. Respects display_mode. Supports HTTP range requests for video seeking.
- `browse()` — JSON: simplified file list for Summernote link/image/video picker.
- `createFolder()` / `renameFolder()` / `deleteFolder()` — Folder CRUD (contributor+). Delete blocked if any file in subtree is in use.
- `moveFile()` / `moveFolder()` — Move items (contributor+). Name conflict and circular reference protection.
- `updateFile()` — Update display_mode, visibility flags, and display name (contributor+).

## RevisionsController

- `index()` — List up to 50 revisions for a page.
- `show()` — Load specific revision for preview (sanitized content).
- `restore()` — Restore revision. Backs up current content as new revision first.

Access: Contributors and Admins always. Editors only when `enableReviewProcess = false`.

## FeedbackController

- `submit()` — Public endpoint, rate-limited (1 per IP per page per hour). Feedback without comment is auto-approved; with comment goes to pending moderation.
- `moderate()` — Approve or reject pending feedback (admin only).
- `pending()` — List pending feedback for moderation (admin only).

## CommentsController

- `index()` — List comments for a page (editor+, requires enableComments).
- `add()` — Add comment (editor+). Processes @mention notifications if enableMentions.
- `delete()` — Delete own comment or any comment as admin.

## MediaController

- `index()` — List all media files with usage tracking, sorted by usage count ascending (editor+).
- `replace()` — Replace file keeping same name and ID (contributor+). File type category must match (e.g. image → image).
