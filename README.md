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

| Key | Type | Description |
|---|---|---|
| `appName` | string | Application title |
| `appLanguage` | string | HTML lang attribute (`en`, `de`) |
| `baseUri` | string | Base URL |
| `editorLanguage` | string | Summernote editor locale |
| `enablePrint` | bool | Show print buttons |
| `showAuthorDetails` | bool | Show author/date info on pages |
| `showLinkButton` | bool | Show "copy link" button |
| `showLoginButton` | bool | Show login button for guests |
| `showNavigationIcons` | bool | Show icons in page tree |
| `showNavigationNumbering` | bool | Show chapter numbers (1.2.3) |
| `showNavigationRoot` | bool | Show root node in tree |
| `showTopNavigation` | bool | Show top navigation bar |
| `useLogo` | bool | Show logo instead of app name |
| `logoPath` | string | Path to logo (relative to webroot) |

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
