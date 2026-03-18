# Guidevera Manual

A self-hosted documentation and knowledge base application built on CakePHP 5.

## Overview

Guidevera provides a hierarchical page management system with a rich feature set for teams that need a structured, self-hosted documentation portal.

## Core Features

- Tree-structured pages with drag-and-drop reordering and chapter numbering
- WYSIWYG editing via Summernote with file/page/image/video browser
- Full-text search and keyword index
- Multi-language support (UI via gettext `.po`, content translations via DB)
- Role-based access control (guest / editor / contributor / admin)
- ID-based file management with folders, visibility controls, and inline display
- Dark mode with system preference detection and font size adjustment
- Resizable sidebar (drag handle, saved as cookie)
- Server-side rendered navigation for SEO and guests
- Responsive design with mobile sidebar drawer

## Extended Features

- Page revisions with diff comparison and one-click restore
- Workflow system (draft → review → published → archived)
- Scheduled publishing and expiration (cron-based)
- Multi-step review process with assigned reviewers
- Page subscriptions with change notifications
- Read acknowledgements for compliance pages (locale-aware)
- Internal comments with @mention notifications
- Inline paragraph-level comments
- Smart internal link suggestions (autocomplete)
- Feedback system (thumbs up/down with moderation)
- Markdown/HTML import
- PDF and Markdown export
- Breadcrumbs and prev/next navigation
- Cookie consent banner (GDPR)
- Dashboard with quality metrics, trash panel, and full page overview table
- Content analytics, audit log, webhooks
- Quality check command with missing description, keyword, and tag detection

## Quick Start

```bash
git clone <repository-url> guidevera
cd guidevera
composer install --no-dev
cp config/app_local.example.php config/app_local.php
# Edit app_local.php: set database credentials + Security salt
bin/cake install
```

See [Installation](installation.md) for details.

## Project Structure

```
src/
├── Controller/
│   ├── AppController.php          # Base: auth, JSON helpers, role checks
│   ├── PagesController.php        # Pages CRUD, tree, dashboard, all page features
│   ├── UsersController.php        # Auth, profile, user management
│   ├── FilesController.php        # File management with folders + ID-based links
│   ├── RevisionsController.php    # Page version history
│   ├── FeedbackController.php     # Feedback submission + moderation
│   ├── CommentsController.php     # Internal page comments
│   └── MediaController.php        # Media library overview + file replacement
├── Service/
│   ├── PagesService.php           # Sanitizer, numbering, navigation, breadcrumbs
│   ├── UploadService.php          # File upload handling
│   └── WebhookService.php         # Webhook dispatch (async queue)
├── Model/Table/                   # ORM table classes (17 tables)
├── Middleware/
│   ├── CspMiddleware.php          # Content Security Policy headers
│   └── HostHeaderMiddleware.php   # Host header injection protection
└── Command/
    ├── InstallCommand.php          # Database setup + admin creation
    ├── PublishSchedulerCommand.php # Auto-publish/expire pages
    ├── QualityCheckCommand.php     # Content quality metrics
    └── WebhookWorkerCommand.php    # Process async webhook queue

templates/
├── layout/default.php             # Main layout (header, dark mode, cookie consent)
├── Pages/                         # index, dashboard, print, sitemap views
├── Users/                         # login, profile, create, manage
├── Files/index.php                # File management with folders
└── element/pages/                 # Reusable: sidebar, show (SSR)

webroot/
├── css/app.css                    # Single stylesheet (~530 lines, CSS variables)
└── js/
    ├── pages.js                   # Client-side SPA module (~1800 lines)
    └── init.js                    # Pre-render: dark mode, font size, sidebar resize
```

## Technology Stack

| Layer      | Technology                          |
|------------|-------------------------------------|
| Backend    | PHP 8.2+, CakePHP 5.3              |
| Database   | MySQL 5.7+ / MariaDB 10.3+         |
| Frontend   | jQuery 3.5, Bootstrap 5, Summernote |
| Auth       | Session-based, HMAC-SHA256 + bcrypt |
| i18n       | CakePHP I18n with gettext `.po`     |
