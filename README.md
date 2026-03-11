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

### 2. Create the MySQL database

Database names should use only letters, numbers, and underscores (no dots).

```bash
mysql -u root -p -e "CREATE DATABASE manual CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
```

### 3. Configure database connection

Copy the example config and edit it:

```bash
cp config/app_local.example.php config/app_local.php
```

In `config/app_local.php`, set your database credentials:

```php
'Datasources' => [
    'default' => [
        'host' => 'localhost',
        'username' => 'your_db_user',
        'password' => 'your_db_password',
        'database' => 'manual',
    ],
],
```

Or use environment variables: `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`.

### 4. Set the Security Salt

Every installation must have a unique, random Security Salt. Generate one and set it
as environment variable or in `config/app_local.php`:

```bash
# Generate a random salt
php -r "echo bin2hex(random_bytes(32));"

# Set as environment variable (add to .env, .bashrc, or server config)
export SECURITY_SALT=your_generated_salt_here
```

Or in `config/app_local.php`:

```php
'Security' => [
    'salt' => 'your_generated_salt_here',
],
```

The installer will warn you if the salt is still the default value.

### 5. Run the installer

```bash
bin/cake install
```

The installer will:

1. Test the database connection
2. Create all 15 database tables
3. Create the initial admin account (username + password shown once in the terminal)
4. Create `tmp/`, `logs/`, `storage/`, `storage/media/`, `storage/ratelimit/`
5. Verify file permissions
6. Warn if the Security Salt is still the default

**Save the admin password immediately — it is only shown once.**

### 6. Configure the web server

Point the web server document root to the `webroot/` directory.

Apache: The `.htaccess` files are already included.

Nginx example:

```nginx
server {
    root /path/to/manual/webroot;
    index index.php;

    location / {
        try_files $uri /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 7. Open in browser and log in

Navigate to your application URL and log in with:

- **Username:** `admin`
- **Password:** *(shown in terminal during step 5)*

You will be prompted to change the password on first login.

### Cron jobs (optional)

For scheduled publishing and content quality checks:

```bash
# Auto-publish/expire pages (every 5 minutes)
*/5 * * * * cd /path/to/manual && bin/cake publish-scheduler

# Content quality report (daily at 2am, results shown in dashboard)
0 2 * * * cd /path/to/manual && bin/cake quality-check
```

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
