# AppProfileSafe Manual

A self-hosted documentation and knowledge base application built on CakePHP 5.

## Overview

AppProfileSafe Manual provides a hierarchical page management system with:

- **Tree-structured pages** with drag-and-drop reordering
- **WYSIWYG editing** via Summernote with file/page link browser
- **Full-text search** and keyword index
- **Multi-language support** (i18n: UI strings via gettext `.po` files, content translations via `page_translations` database table)
- **Role-based access control** (guest/editor/contributor/admin roles)
- **Print export** (single page or complete book)
- **SEO-friendly** server-side rendered navigation for guests
- **Responsive design** with mobile sidebar drawer

## Quick Start

```bash
git clone <repository-url> manual
cd manual
composer install --no-dev
# Configure database in config/app.php
bin/cake install
```

See [Installation](installation.md) for detailed setup instructions.

## Project Structure

```
src/
├── Controller/
│   ├── AppController.php        # Base controller (auth, JSON helpers)
│   ├── PagesController.php      # Page CRUD and tree operations
│   ├── UsersController.php      # Authentication and user management
│   ├── FilesController.php      # File upload/download
│   └── Component/
│       └── PageContentComponent.php  # Search, index, print operations
├── Service/
│   └── PagesService.php         # HTML sanitizer, chapter numbering, navigation
├── Model/
│   ├── Table/                   # ORM table classes
│   └── Entity/                  # Entity classes
├── Middleware/
│   └── HostHeaderMiddleware.php # Host header injection protection
└── Command/
    └── InstallCommand.php       # Database setup command

templates/
├── layout/default.php           # Main layout with header, notifications, JS/CSS
├── Pages/index.php              # Pages main view (SSR + JS hydration)
├── Users/                       # Login, profile, user management views
├── Files/index.php              # File management view
└── element/pages/               # Reusable page components (sidebar, show)

webroot/
├── css/app.css                  # Consolidated stylesheet (single file)
└── js/pages.js                  # Client-side page module (IIFE, JSON APIs)
```

## Key Features

- Page tree with drag-and-drop reordering and chapter numbering
- WYSIWYG editor (Summernote) with inline media upload
- Fulltext search with highlighting, snippets, and filters
- Page revisions with two-version diff comparison
- Workflow system (draft → review → published → archived)
- Tags and related pages (automatic cross-referencing)
- Content translations (multi-locale support)
- 4-role authorization (guest/editor/contributor/admin)
- Internal page comments with @mention notifications
- Feedback system (thumbs up/down + moderation)
- Dashboard with quality metrics (stale pages, missing descriptions)
- Content quality checks via CLI (`bin/cake quality-check`)
- Audit log for all administrative actions
- PDF and Markdown export
- Dark mode with system preference detection
- Cookie consent banner (GDPR-ready)
- PWA/offline support via Service Worker
- CSP headers with nonce-based script policy
- Scheduled publishing and expiration (cron-based)
- Multi-step review process with assigned reviewers
- Page subscriptions with change notifications
- Read acknowledgements for compliance pages
- Inline paragraph-level comments
- Central media library with usage tracking
- Markdown/HTML import
- Webhooks for external integrations
- Content analytics (views, feedback, update frequency)
- Translation status tracking (missing/stale translations)
- Smart internal link suggestions

## Technology Stack

| Layer      | Technology                           |
|------------|--------------------------------------|
| Backend    | PHP 8.1+, CakePHP 5                 |
| Database   | MySQL 5.7+ / MariaDB 10.3+          |
| Frontend   | jQuery 3.5, Bootstrap 5, Summernote  |
| Auth       | Session-based, HMAC-SHA256 + bcrypt  |
| i18n       | CakePHP I18n with gettext `.po`      |
