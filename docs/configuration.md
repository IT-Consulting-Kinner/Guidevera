# Configuration

All application settings are in `config/app.php`. Environment-specific overrides can be placed in `config/app_local.php` (not committed to version control).

## Manual Settings

The `Manual` block in `config/app.php` controls all UI behavior:

```php
'Manual' => [
    'appName' => 'My Documentation',
    'appLanguage' => 'en',
    'textDirection' => 'ltr',
    'baseUri' => '/',
    'editorLanguage' => 'en-US',
    'enablePrint' => false,
    'showAuthorDetails' => false,
    'showLinkButton' => true,
    'showLoginButton' => true,
    'showNavigationIcons' => false,
    'showNavigationNumbering' => false,
    'showNavigationRoot' => false,
    'showTopNavigation' => true,
    'useLogo' => false,
    'logoPath' => '/img/logo.png',
],
```

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `appName` | string | 'AppProfileSafe' | Application title (shown in header and browser tab) |
| `appLanguage` | string | 'en' | HTML `lang` attribute value |
| `textDirection` | string | 'ltr' | Text direction: 'ltr' or 'rtl' |
| `baseUri` | string | '/' | Base URL path |
| `editorLanguage` | string | 'en-US' | Summernote editor locale |
| `enablePrint` | bool | false | Show print buttons in toolbar |
| `showAuthorDetails` | bool | true | Show author name and timestamps |
| `showLinkButton` | bool | true | Show "Copy link" button in toolbar |
| `showLoginButton` | bool | true | Show login button for guests |
| `showNavigationIcons` | bool | true | Show folder/document icons in tree |
| `showNavigationNumbering` | bool | true | Prefix titles with chapter numbers (1.2.3) |
| `showNavigationRoot` | bool | true | Show root node in navigation tree |
| `showTopNavigation` | bool | true | Show Pages/Index/Search tabs |
| `useLogo` | bool | false | Show logo image instead of appName text |
| `logoPath` | string | '' | Path to logo image (relative to webroot) |

## Database Settings

```php
'Datasources' => [
    'default' => [
        'host' => 'localhost',
        'port' => '3306',
        'username' => 'manual',
        'password' => '',
        'database' => 'manual',
    ],
],
```

For production, override in `config/app_local.php`:

```php
return [
    'Datasources' => [
        'default' => [
            'driver' => \Cake\Database\Driver\Mysql::class,
            'host' => 'localhost',
            'username' => 'production_user',
            'password' => 'secret',
            'database' => 'production_db',
        ],
    ],
];
```

## Locale Settings

```php
'App' => [
    'defaultLocale' => 'de_DE',      // UI language
    'defaultTimezone' => 'Europe/Berlin',
],
```

Translation files are in `resources/locales/{locale}/default.po`.

## Security Settings

```php
'Security' => [
    'salt' => 'your-unique-random-string-here',
],
```

Generate a unique salt for each installation. This is used by CakePHP's internal hashing (cookies, CSRF tokens). The password HMAC salt is a separate constant in `UsersController`.


## Feature Flags

All v11 features can be enabled/disabled in `config/app.php` under the `Manual` key:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enableScheduledPublishing` | bool | `false` | Auto-publish/expire pages via `bin/cake publish-scheduler` cron job |
| `enableReviewProcess` | bool | `false` | Multi-step review with assigned reviewers, approve/reject with comments |
| `enableSubscriptions` | bool | `true` | Users can subscribe to pages and receive change notifications |
| `enableAcknowledgements` | bool | `false` | "Read and understood" confirmation for compliance/training pages |
| `enableInlineComments` | bool | `false` | Paragraph-level comments for review discussions |
| `enableMediaLibrary` | bool | `true` | Central media browser with usage tracking (`/media`) |
| `enableImport` | bool | `true` | Import pages from Markdown or HTML files |
| `enableWebhooks` | bool | `false` | HTTP POST to external URLs on page events (update, review) |
| `enableContentAnalytics` | bool | `true` | Admin dashboard: top/least viewed, bad feedback, frequently updated |
| `enableSmartLinks` | bool | `true` | Autocomplete for internal page links in the editor |
| `staleContentMonths` | int | `12` | Pages not updated in this many months are flagged as stale |

### Cron Jobs

For scheduled publishing, add to crontab:

```
*/5 * * * * cd /path/to/app && bin/cake publish-scheduler
```

For quality checks (updates dashboard metrics):

```
0 2 * * * cd /path/to/app && bin/cake quality-check
```
