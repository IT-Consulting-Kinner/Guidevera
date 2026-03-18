# Guidevera

A self-hosted documentation and knowledge base application built on CakePHP 5. Hierarchical page management, WYSIWYG editing, full-text search, role-based access, and a comprehensive feature set for teams.

## Requirements

- PHP 8.2+ (extensions: intl, mbstring, pdo_mysql)
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- Apache with mod_rewrite or Nginx

## Quick Start

```bash
git clone <repository-url> guidevera
cd guidevera
composer install --no-dev
cp config/app_local.example.php config/app_local.php
```

Edit `config/app_local.php` — set database credentials and Security salt:

```php
return [
    'Datasources' => [
        'default' => [
            'host' => 'localhost',
            'username' => 'your_db_user',
            'password' => 'your_db_password',
            'database' => 'guidevera',
        ],
    ],
    'Security' => [
        'salt' => 'your-unique-random-string-at-least-64-characters',
    ],
];
```

Run the installer:

```bash
sudo bin/cake install --webuser=www-data
```

The installer creates all 17 database tables, an admin account (credentials shown in terminal), storage directories, and sets file ownership for the webserver user.

**Important:**
- Security salt **must** be in `app_local.php`, not as an environment variable.
- Database name must not contain dots.
- Admin password must be changed on first login.

## Features

### Core
- Tree-structured pages with drag-and-drop reordering and chapter numbering
- WYSIWYG editor (Summernote) with file/page/image/video browser
- Full-text search with highlighting and keyword index
- Server-side rendered navigation for SEO and guests
- Responsive layout with mobile sidebar drawer
- Resizable sidebar (drag handle, width saved as cookie)

### Content Management
- Page revisions with diff comparison and one-click restore
- Workflow system (draft → review → published → archived)
- Scheduled publishing and expiration via cron
- Multi-step review process with assigned reviewers
- Markdown/HTML import
- PDF and Markdown export
- Content translations with stale-translation tracking
- Breadcrumbs and prev/next navigation
- Smart internal link suggestions (autocomplete in editor)

### File Management
- ID-based file links (`/downloads/{id}/{name}`) — moving/renaming never breaks links
- Unlimited folder nesting with drag & drop
- Per-file display mode (inline in browser vs forced download)
- Per-file visibility (checkboxes: Guest, Editor, Contributor, Admin)
- Usage tracking (which pages reference each file)
- Download counter (guest-only for analytics accuracy)
- Browse files from Summernote link, image, and video dialogs (filtered by media type)

### Collaboration
- Internal page comments with @mention notifications
- Inline paragraph-level comments with text anchoring
- Page subscriptions with change notifications
- Read acknowledgements for compliance pages (locale-aware, with reporting)
- Feedback system (thumbs up/down with moderation)

### Administration
- Dashboard with quality metrics, trash panel, and full page overview table
- Quality report: stale pages, missing descriptions, missing keywords, missing tags
- Audit log for all actions
- Content analytics (views, feedback, update frequency)
- Webhooks for external integrations (async queue, SSRF protected)
- Trash with soft delete and auto-purge

### UI
- Dark mode with system preference detection
- Adjustable font size (A-/A+) — all dimensions scale via rem
- Cookie consent banner (GDPR)
- Configurable via 40+ settings in `config/app.php`

## Role System

| | Editor | Contributor | Admin |
|---|---|---|---|
| View/search pages | ✓ | ✓ | ✓ |
| Edit/save pages | ✓ | ✓ | ✓ |
| Create/delete/reorder pages | — | ✓ | ✓ |
| Set page active/inactive | — | ✓ | ✓ |
| Upload files | ✓ | ✓ | ✓ |
| Delete files / manage folders | — | ✓ | ✓ |
| Page revisions (no workflow) | ✓ | ✓ | ✓ |
| Page revisions (with workflow) | — | ✓ | ✓ |
| Trash restore | — | ✓ | ✓ |
| Trash purge / user management | — | — | ✓ |

Editors with workflow enabled: saving sets the page to "In Review" automatically.

## Configuration

All settings are in `config/app.php` under the `Manual` key. Example:

```php
'Manual' => [
    'appName' => 'Guidevera',
    'appLanguage' => 'en',
    'enablePrint' => true,
    'enableFeedback' => true,
    'enableRevisions' => true,
    'enableDarkMode' => true,
    'enableComments' => true,
    'enableMentions' => true,
    'enableFontSize' => true,
    'enableBreadcrumbs' => true,
    'enableCookieConsent' => true,
    'enableReviewProcess' => false,
    'enableScheduledPublishing' => false,
    'enableSubscriptions' => true,
    'enableAcknowledgements' => false,
    'enableInlineComments' => false,
    'enableSmartLinks' => true,
    'enableImport' => true,
    'enableAuditLog' => true,
    'enableWebhooks' => false,
    'enableContentAnalytics' => true,
    'enableTranslations' => false,
    'contentLocales' => ['en'],
    'maxUploadSize' => 10485760,
    'staleContentMonths' => 12,
    'trashRetentionDays' => 30,
    'showNavigationRoot' => false,
    'useLogo' => false,
    'logoPath' => '/img/logo.webp',
],
```

See `docs/configuration.md` for the full reference of all 40+ settings.

**Logo:** Set `useLogo = true` and `logoPath` to the path of your logo relative to `webroot/` with a leading `/` (e.g. `/img/logo.webp`). The path is used directly as an `<img src>` attribute.

## Cron Jobs

```cron
# Auto-publish/expire pages (every 5 min)
*/5 * * * * cd /path/to/guidevera && bin/cake publish-scheduler

# Content quality metrics + trash purge (nightly)
0 2 * * *   cd /path/to/guidevera && bin/cake quality-check
```

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+, CakePHP 5.3 |
| Database | MySQL / MariaDB (InnoDB, utf8mb4) |
| Frontend | jQuery 3.5, Bootstrap 5, Summernote 0.8.18 |
| Auth | Session-based, HMAC-SHA256 + bcrypt |
| i18n | CakePHP I18n with gettext `.po` files |
| Security | CSP with nonces, CSRF, rate limiting, HTML sanitization |

## Project Structure

```
src/Controller/          7 controllers
src/Service/             3 services (PagesService, UploadService, WebhookService)
src/Command/             4 CLI commands (install, publish-scheduler, quality-check, webhook-worker)
src/Model/Table/         17 ORM table classes
src/Middleware/          2 middleware (CSP, Host header)
db/schema.sql            17 tables
templates/               Layout, Pages, Users, Files, elements
webroot/css/app.css      Single stylesheet (~530 lines, CSS variables, dark mode)
webroot/js/pages.js      SPA module (~1800 lines, IIFE)
webroot/js/init.js       Pre-render features: dark mode, font size, sidebar resize
docs/                    DocFx documentation (10 pages)
config/app.php           All application settings
```

## Documentation

Full documentation is in the `docs/` folder (DocFx format):

- [Introduction](docs/index.md)
- [Architecture](docs/architecture.md)
- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Controllers API](docs/api/controllers.md)
- [Database Schema](docs/database.md)
- [Security](docs/security.md)
- [JavaScript](docs/frontend/javascript.md)
- [CSS Architecture](docs/frontend/css.md)

## License

Proprietary. All rights reserved.
