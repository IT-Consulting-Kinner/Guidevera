# AppProfileSafe Manual

A CakePHP-based documentation and knowledge base application with hierarchical page management, WYSIWYG editing, full-text search, and multi-language support.

## Requirements

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- Apache or Nginx with URL rewriting enabled

## Installation

### 1. Clone and install dependencies

```bash
git clone <repository-url> manual
cd manual
composer install --no-dev
```

### 2. Configure the database

Edit `config/app.php` and set your database credentials in the `Datasources.default` section:

```php
'host' => 'localhost',
'username' => 'your_db_user',
'password' => 'your_db_password',
'database' => 'your_db_name',
```

Or use environment variables: `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`.

### 3. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE manual CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
```

### 4. Run the installer

```bash
bin/cake install
```

This creates all database tables and verifies the setup.

### 5. Set file permissions

```bash
chmod -R 775 tmp/ logs/
mkdir -p storage && chmod 775 storage/
```

### 6. Open in browser

The install command creates the database tables and the initial admin account.
The admin password is displayed once in the terminal — save it immediately.
Log in with:

- **Username:** `admin`
- **Password:** *(shown on screen)*

You will be prompted to change the password immediately.

## Configuration

All settings are in `config/app.php` under the `Manual` section:

### General

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `appName` | string | `'AppProfileSafe'` | Application title |
| `appLanguage` | string | `'en'` | HTML lang attribute (`en`, `de`) |
| `textDirection` | string | `'ltr'` | Text direction (`ltr`, `rtl`) |
| `baseUri` | string | `'/'` | Base URL path |
| `editorLanguage` | string | `'en-US'` | Summernote editor locale |

### Navigation & Display

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `showAuthorDetails` | bool | `false` | Show author/date info on pages |
| `showLinkButton` | bool | `true` | Show "copy link" button |
| `showLoginButton` | bool | `true` | Show login button for guests |
| `showNavigationIcons` | bool | `true` | Show icons in page tree |
| `showNavigationNumbering` | bool | `true` | Show chapter numbers (1.2.3) |
| `showNavigationRoot` | bool | `true` | Show root node in tree |
| `showTopNavigation` | bool | `true` | Show top navigation bar |
| `useLogo` | bool | `false` | Show logo instead of app name |
| `logoPath` | string | `''` | Path to logo (relative to webroot) |
| `enableBreadcrumbs` | bool | `true` | Breadcrumb navigation on pages |
| `enablePrevNext` | bool | `true` | Previous/Next page buttons |
| `enableDarkMode` | bool | `true` | Dark mode toggle in header |
| `enableFontSize` | bool | `true` | Font size A-/A+ buttons |
| `enablePrint` | bool | `false` | Print page/book buttons |

### Content Features

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enableRevisions` | bool | `true` | Page version history with diff |
| `enableFeedback` | bool | `true` | Thumbs up/down + comments |
| `enableComments` | bool | `true` | Internal page comments for editors |
| `enableMentions` | bool | `true` | @username mentions in comments |
| `enableTranslations` | bool | `false` | Multi-language content support |
| `contentLocales` | array | `['en']` | Available content languages |
| `defaultLocale` | string | `'en'` | Default content language |
| `enablePdfExport` | bool | `false` | PDF export (requires wkhtmltopdf) |
| `enableMarkdownExport` | bool | `true` | Markdown export per page |
| `enableImport` | bool | `true` | Import pages from Markdown/HTML |
| `enableSmartLinks` | bool | `true` | Autocomplete for internal links |

### Workflow & Review

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enableScheduledPublishing` | bool | `false` | Auto-publish/expire via cron |
| `enableReviewProcess` | bool | `false` | Assign reviewers, approve/reject |
| `enableSubscriptions` | bool | `true` | Subscribe to page changes |
| `enableAcknowledgements` | bool | `false` | "Read and understood" confirmation |
| `enableInlineComments` | bool | `false` | Paragraph-level review comments |

### Administration

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enableAuditLog` | bool | `true` | Log all admin actions |
| `enableContentAnalytics` | bool | `true` | Views/feedback analytics dashboard |
| `enableMediaLibrary` | bool | `true` | Central media browser at `/media` |
| `enableCookieConsent` | bool | `true` | GDPR cookie consent banner |
| `enableWebhooks` | bool | `false` | HTTP POST on page events |
| `maxUploadSize` | int | `10485760` | Max upload size in bytes (10 MB) |
| `notifyEmail` | string | `''` | Email for admin notifications (empty = disabled) |
| `trashRetentionDays` | int | `30` | Days before trashed pages are purged |
| `staleContentMonths` | int | `12` | Months before pages are flagged as stale |

### Language

Set `App.defaultLocale` in `config/app.php`:

```php
'defaultLocale' => 'de_DE',  // German
'defaultLocale' => 'en_US',  // English (default)
```

Translation files are in `resources/locales/{locale}/default.po` (UI strings) and the `page_translations` table (content).

## File Uploads

Uploaded files are stored in `storage/`. Ensure it is writable by the web server. Downloads are served through the application controller.

## Apache

```apache
<Directory /path/to/manual/webroot>
    AllowOverride All
    Require all granted
</Directory>
```

## Nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```
