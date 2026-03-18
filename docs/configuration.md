# Configuration

All settings are in `config/app.php` under the `Manual` key. Environment-specific overrides go in `config/app_local.php`.

## Application Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `appName` | string | `'Guidevera'` | Application title (header + browser tab) |
| `appLanguage` | string | `'en'` | HTML `lang` attribute |
| `textDirection` | string | `'ltr'` | `'ltr'` or `'rtl'` |
| `baseUri` | string | `'/'` | Base URL path |
| `editorLanguage` | string | `'en-US'` | Summernote editor locale |

## UI Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `showAuthorDetails` | bool | `true` | Show creator/modifier name and timestamps |
| `showLinkButton` | bool | `true` | Show "Copy link" button in toolbar |
| `showLoginButton` | bool | `true` | Show login button for guests |
| `showNavigationIcons` | bool | `true` | Folder/document icons in page tree |
| `showNavigationNumbering` | bool | `true` | Chapter numbers (1.2.3) in tree |
| `showNavigationRoot` | bool | `true` | Show root node in tree |
| `showTopNavigation` | bool | `true` | Pages/Index/Search tabs in sidebar |
| `useLogo` | bool | `false` | Logo image instead of appName text |
| `logoPath` | string | `''` | Path to logo (relative to webroot) |
| `enableDarkMode` | bool | `true` | Show dark mode toggle in header |
| `enableFontSize` | bool | `true` | Show A-/A+ font size buttons |
| `enableCookieConsent` | bool | `true` | GDPR cookie consent banner |
| `enableBreadcrumbs` | bool | `true` | Breadcrumb path below toolbar |
| `enablePrevNext` | bool | `true` | Previous/Next page navigation |

## Feature Flags

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enablePrint` | bool | `true` | Print buttons (single page + book) |
| `enableFeedback` | bool | `true` | Thumbs up/down feedback per page |
| `enableRevisions` | bool | `true` | Page version history with diff |
| `enableComments` | bool | `false` | Internal comments per page |
| `enableMentions` | bool | `false` | @username autocomplete in comments |
| `enableTranslations` | bool | `false` | Multi-locale content translations |
| `enableMarkdownExport` | bool | `false` | Markdown export button |
| `enablePdfExport` | bool | `false` | PDF export (requires wkhtmltopdf) |
| `enableReviewProcess` | bool | `false` | Workflow: assign reviewers, approve/reject |
| `enableSubscriptions` | bool | `false` | Subscribe to page changes |
| `enableAcknowledgements` | bool | `false` | "Read and understood" confirmations |
| `enableInlineComments` | bool | `false` | Paragraph-level review comments |
| `enableImport` | bool | `false` | Import pages from Markdown/HTML files |
| `enableSmartLinks` | bool | `false` | Autocomplete for internal links in editor |
| `enableScheduledPublishing` | bool | `false` | Auto-publish/expire via cron |
| `enableWebhooks` | bool | `false` | HTTP POST on page events |
| `enableContentAnalytics` | bool | `false` | Admin analytics dashboard |
| `enableAuditLog` | bool | `false` | Log all admin actions |

## Translation Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `contentLocales` | array | `['en']` | Available content languages |
| `defaultLocale` | string | `'en'` | Primary content language |

## Upload Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `maxUploadSize` | int | `10485760` | Max upload size in bytes (10 MB) |

## Quality Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `staleContentMonths` | int | `12` | Pages older than this are flagged stale |
| `trashRetentionDays` | int | `30` | Auto-purge trash after this many days |

## Notification Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `notifyEmail` | string | `''` | Email for admin notifications (empty = disabled) |

## Database Settings

Configure in `config/app_local.php`:

```php
'Datasources' => [
    'default' => [
        'driver' => \Cake\Database\Driver\Mysql::class,
        'host' => 'localhost',
        'username' => 'guidevera',
        'password' => 'secret',
        'database' => 'guidevera',
        'timezone' => 'UTC',
    ],
],
```

## Security Settings

```php
'Security' => [
    'salt' => 'your-unique-64-char-random-string',
],
```

Must be set in `app_local.php`. Each installation needs a unique salt.
