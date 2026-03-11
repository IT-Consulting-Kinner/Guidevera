<?php

declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

/**
 * Install command — creates database tables and the initial admin account.
 *
 * Usage: bin/cake install
 */
class InstallCommand extends Command
{
    public static function defaultName(): string
    {
        return 'install';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Initialize the database schema, create the initial admin account, and verify setup.');
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $io->out('');
        $io->out('<info>AppProfileSafe Manual — Installation</info>');
        $io->out(str_repeat('─', 50));
        $io->out('');

        // 1. Test database connection
        $io->out('1. Testing database connection...');
        try {
            $conn = ConnectionManager::get('default');
            $conn->getDriver()->connect();
            $io->success('   Database connection OK.');
        } catch (\Exception $e) {
            $io->error('   Database connection FAILED: ' . $e->getMessage());
            $io->out('   Configure your database in config/app_local.php or set DB_HOST, DB_USERNAME, DB_PASSWORD,
                DB_DATABASE');
            return self::CODE_ERROR;
        }

        // 2. Create tables from schema.sql
        $io->out('');
        $io->out('2. Creating database tables...');
        $schemaFile = ROOT . DS . 'db' . DS . 'schema.sql';
        if (!file_exists($schemaFile)) {
            $io->error('   Schema file not found: ' . $schemaFile);
            return self::CODE_ERROR;
        }

        $sql = file_get_contents($schemaFile);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $created = $existed = 0;

        foreach ($statements as $stmt) {
            // Strip SQL comment lines from statement
            $lines = explode("\n", $stmt);
            $cleaned = [];
            foreach ($lines as $line) {
                if (!str_starts_with(trim($line), '--')) {
                    $cleaned[] = $line;
                }
            }
            $stmt = trim(implode("\n", $cleaned));
            if (empty($stmt)) {
                continue;
            }
            try {
                $conn->execute($stmt);
                if (stripos($stmt, 'CREATE TABLE') !== false) {
                    preg_match('/`(\w+)`/', $stmt, $m);
                    $io->out("   ✓ Table '{$m[1]}' ready.");
                    $created++;
                }
            } catch (\Exception $e) {
                if (stripos($e->getMessage(), 'already exists') !== false) {
                    preg_match('/`(\w+)`/', $stmt, $m);
                    $io->out("   · Table '{$m[1]}' already exists.");
                    $existed++;
                } else {
                    $io->warning('   ! ' . $e->getMessage());
                }
            }
        }
        $io->success("   Schema applied ({$created} created, {$existed} already existed).");

        // 3. Create initial admin account (if no users exist)
        $io->out('');
        $io->out('3. Admin account...');
        $users = $this->fetchTable('Users');
        $count = $users->find()->count();
        if ($count === 0) {
            $password = bin2hex(random_bytes(8));
            $salt = \Cake\Utility\Security::getSalt();
            $hashedPassword = password_hash(hash_hmac('sha256', $password, $salt), PASSWORD_DEFAULT);

            $admin = $users->newEntity([
                'gender' => 'male',
                'username' => 'admin',
                'password' => $hashedPassword,
                'fullname' => 'Administrator',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'status' => 'active',
                'change_password' => 1,
                'page_tree' => '',
                'notify_mentions' => 1,
                'preferences' => '{}',
            ]);

            if ($users->save($admin)) {
                $io->out('');
                $io->out('   ┌─────────────────────────────────────┐');
                $io->out('   │  <info>Initial Admin Account Created</info>       │');
                $io->out('   │                                     │');
                $io->out("   │  Username: <info>admin</info>                    │");
                $io->out("   │  Password: <info>{$password}</info>      │");
                $io->out('   │                                     │');
                $io->out('   │  ⚠ Save this password now!          │');
                $io->out('   │  You will be asked to change it     │');
                $io->out('   │  on first login.                    │');
                $io->out('   └─────────────────────────────────────┘');
                $io->out('');
            } else {
                $io->error('   Failed to create admin account.');
                return self::CODE_ERROR;
            }
        } else {
            $adminCount = $users->find()->where(['role' => 'admin'])->count();
            $io->success("   {$count} user(s) found ({$adminCount} admin(s)).");
        }

        // 4. Check writable directories
        $io->out('4. Checking file permissions...');
        foreach (['tmp' => ROOT . DS . 'tmp', 'logs' => ROOT . DS . 'logs'] as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $io->out(is_writable($path) ? "   ✓ {$label}/ writable." : "   ✗ {$label}/ NOT writable — chmod -R 775
                {$path}");
        }

        // 5. Check storage directory
        $io->out('');
        $io->out('5. Checking storage directory...');
        $storagePath = ROOT . DS . 'storage';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0775, true);
        }
        $mediaPath = $storagePath . DS . 'media';
        if (!is_dir($mediaPath)) {
            mkdir($mediaPath, 0775, true);
        }
        $io->out(is_writable($storagePath) ? '   ✓ storage/ writable.' : "   ✗ storage/ NOT writable — chmod 775
            {$storagePath}");

        // 6. Security salt check
        $io->out('');
        $io->out('6. Security check...');
        $salt = \Cake\Utility\Security::getSalt();
        if ($salt === 'ab65982f846df40f37417be06b12bd942847aa9ee2e5871bb6f2ff1369cc929e') {
            $io->warning('   ⚠ Security.salt is still the default value!');
            $io->warning('   Set SECURITY_SALT environment variable to a unique random string.');
            $io->warning('   Generate one with: php -r "echo bin2hex(random_bytes(32));"');
        } else {
            $io->success('   Security.salt is configured.');
        }

        // Summary
        $io->out('');
        $io->out(str_repeat('─', 50));
        $io->success('Installation complete.');
        $io->out('');
        $io->out('Next steps:');
        $io->out('  1. Open your application in a browser');
        if ($count === 0) {
            $io->out('  2. Log in with the admin credentials shown above');
            $io->out('  3. Change the password when prompted');
        } else {
            $io->out('  2. Log in with your existing admin account');
        }
        $io->out('');

        return self::CODE_SUCCESS;
    }
}
