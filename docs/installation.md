# Installation

## Requirements

- PHP 8.1 or higher with extensions: intl, mbstring, pdo_mysql
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite or Nginx
- Composer

## Steps

### 1. Install Dependencies

```bash
composer install --no-dev
```

### 2. Configure Database + Salt

```bash
cp config/app_local.example.php config/app_local.php
```

Edit `config/app_local.php`:

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

**Critical:** The Security salt MUST be set in `app_local.php`, not as an environment variable. Environment variables are not available to the webserver process (Apache/PHP-FPM), causing CLI and webserver to use different salts which breaks login.

**Database name:** Do not use dots in the database name — MySQL interprets dots as schema separators.

### 3. Run Installer

```bash
bin/cake install
```

This creates all 17 tables, the admin account, and required storage directories. Credentials are shown in the terminal.

### 4. Set Permissions

```bash
chown -R www-data:www-data storage/ tmp/ logs/
```

### 5. Configure Webserver

Set DocumentRoot to the `webroot/` directory.

Apache (`.htaccess` files are included):
```apache
<VirtualHost *:80>
    DocumentRoot /path/to/guidevera/webroot
    <Directory /path/to/guidevera/webroot>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 6. Configure Cron Jobs (Optional)

```cron
*/5 * * * * cd /path/to/guidevera && bin/cake publish-scheduler
0 2 * * *   cd /path/to/guidevera && bin/cake quality-check
```

## Upgrading

When upgrading from an earlier version, apply schema migrations manually:

```sql
-- Add media_folders table
CREATE TABLE IF NOT EXISTS media_folders (...);

-- Add new columns to media_files
ALTER TABLE media_files
  ADD COLUMN folder_id bigint UNSIGNED DEFAULT NULL,
  ADD COLUMN display_mode varchar(10) NOT NULL DEFAULT 'download',
  ADD COLUMN visible_guest tinyint(1) NOT NULL DEFAULT 1,
  ADD COLUMN visible_editor tinyint(1) NOT NULL DEFAULT 1,
  ADD COLUMN visible_contributor tinyint(1) NOT NULL DEFAULT 1,
  ADD COLUMN visible_admin tinyint(1) NOT NULL DEFAULT 1,
  ADD COLUMN download_count int UNSIGNED NOT NULL DEFAULT 0;

-- Convert timestamps to datetime (if upgrading from timestamp-based schema)
ALTER TABLE pages MODIFY created datetime NOT NULL, MODIFY modified datetime NOT NULL;
```

Then clear the cache: `bin/cake cache clear_all`
