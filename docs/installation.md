# Installation

## Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Composer 2.x
- Apache with `mod_rewrite` or Nginx with URL rewriting

## Step-by-Step Setup

### 1. Clone the Repository

```bash
git clone <repository-url> manual
cd manual
```

### 2. Install Dependencies

```bash
composer install --no-dev
```

### 3. Configure Database

Create a MySQL database:

```bash
mysql -u root -p -e "CREATE DATABASE manual CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
```

Edit `config/app_local.php` (create it if it doesn't exist):

```php
<?php
return [
    'Datasources' => [
        'default' => [
            'driver' => \Cake\Database\Driver\Mysql::class,
            'host' => 'localhost',
            'username' => 'your_db_user',
            'password' => 'your_db_password',
            'database' => 'your_db_name',
            'encoding' => 'utf8mb4',
        ],
    ],
];
```

### 4. Run the Installer

```bash
bin/cake install
```

This command:
1. Tests the database connection
2. Creates all tables from `db/schema.sql`
3. Creates the initial admin account (username and password shown in terminal output)
4. Verifies file permissions on tmp/, logs/, storage/

### 5. Set File Permissions

```bash
chmod -R 775 tmp/ logs/
mkdir -p storage/media storage/ratelimit
chmod -R 775 storage/
```

### 6. Configure Web Server

#### Apache

Ensure `mod_rewrite` is enabled and `.htaccess` overrides are allowed:

```apache
<Directory /path/to/manual/webroot>
    AllowOverride All
    Require all granted
</Directory>
```

The included `webroot/.htaccess` handles URL rewriting.

#### Nginx

```nginx
server {
    root /path/to/manual/webroot;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Block access to storage directory
    location /storage/ {
        deny all;
    }
}
```

### 7. First Login

1. Open the application in a browser
2. The admin password is displayed once in the terminal during `bin/cake install` — save it immediately
3. Log in with username `admin` and the displayed password
4. You will be prompted to change the password immediately

## Running Tests

```bash
composer require --dev phpunit/phpunit
vendor/bin/phpunit
```

Test suites:
- `tests/TestCase/Service/PagesServiceTest.php` — Sanitizer, numbering, navigation
- `tests/TestCase/Controller/PagesControllerTest.php` — API endpoints, auth guards
- `tests/TestCase/Controller/UsersControllerTest.php` — Login, CSRF, redirects
