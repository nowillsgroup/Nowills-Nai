<?php

namespace App\Support;

use PDO;
use RuntimeException;
use Throwable;

/**
 * One-time browser installer for the prebuilt cPanel package.
 *
 * The normal Git/VPS installers do not use this class. It never runs Composer,
 * never calls shell commands, and never drops or overwrites an existing estate.
 * The public entry point supplies a per-package setup token; the token hash is
 * generated at package build time and the plaintext lives only in SETUP-TOKEN.txt.
 */
class WebInstaller
{
    public const VERSION = '1.0.0';

    public function __construct(private string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    public function isInstalled(): bool
    {
        return is_file($this->root.'/storage/app/installed.lock');
    }

    /** @return array{label:string,ok:bool,hint:string}[] */
    public function requirements(): array
    {
        $checks = [[
            'label' => 'PHP 8.2 or newer',
            'ok' => PHP_VERSION_ID >= 80200,
            'hint' => 'Select PHP 8.3 or 8.4 in cPanel MultiPHP Manager. Detected: '.PHP_VERSION,
        ]];

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'ctype', 'fileinfo', 'tokenizer', 'dom', 'xml', 'xmlwriter', 'curl', 'zip', 'session', 'filter', 'iconv'] as $extension) {
            $checks[] = [
                'label' => 'PHP extension: '.$extension,
                'ok' => extension_loaded($extension),
                'hint' => 'Enable '.$extension.' for this domain in cPanel Select PHP Version / MultiPHP Manager.',
            ];
        }

        $checks[] = [
            'label' => 'Bundled application packages',
            'ok' => is_file($this->root.'/build/vendor/autoload.php'),
            'hint' => 'build/vendor/autoload.php is missing. Re-extract the complete ZIP; Composer is not required.',
        ];

        foreach (['storage', 'storage/framework', 'storage/framework/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app', 'bootstrap/cache', 'public', 'public/uploads'] as $directory) {
            $path = $this->root.'/'.$directory;
            $checks[] = [
                'label' => 'Writable: '.$directory,
                'ok' => is_dir($path) && is_writable($path),
                'hint' => 'Create '.$directory.' if missing and set it to 755 under the cPanel account owner.',
            ];
        }

        $checks[] = [
            'label' => 'Project root writable for .env',
            'ok' => is_dir($this->root) && is_writable($this->root),
            'hint' => 'The project root must be owned by, and writable for, the cPanel PHP user.',
        ];

        return $checks;
    }

    /** @return array{data:array<string,string>,errors:string[]} */
    public function validate(array $input): array
    {
        $errors = [];
        $config = $this->config();
        $token = trim((string) ($input['setup_token'] ?? ''));
        $expected = (string) ($config['setup_token_hash'] ?? '');
        if ($expected === '' || ! preg_match('/^[a-f0-9]{48}$/D', $token) || ! hash_equals($expected, hash('sha256', $token))) {
            $errors[] = 'The setup token is invalid. Copy it from SETUP-TOKEN.txt in the project root using cPanel File Manager.';
        }

        $data = [
            'site_url' => rtrim(trim((string) ($input['site_url'] ?? '')), '/'),
            'workspace' => trim((string) ($input['workspace'] ?? '')),
            'db_host' => trim((string) ($input['db_host'] ?? 'localhost')),
            'db_port' => trim((string) ($input['db_port'] ?? '3306')),
            'db_name' => trim((string) ($input['db_name'] ?? '')),
            'db_user' => trim((string) ($input['db_user'] ?? '')),
            'db_password' => (string) ($input['db_password'] ?? ''),
            'admin_name' => trim((string) ($input['admin_name'] ?? '')),
            'admin_email' => strtolower(trim((string) ($input['admin_email'] ?? ''))),
            'admin_password' => (string) ($input['admin_password'] ?? ''),
        ];
        $confirm = (string) ($input['admin_password_confirm'] ?? '');

        $parts = parse_url($data['site_url']);
        if (! filter_var($data['site_url'], FILTER_VALIDATE_URL) || ! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '/')) {
            $errors[] = 'Enter the site URL as https://your-domain.com with no path, query, or embedded credentials.';
        }
        if ($data['workspace'] === '' || $this->length($data['workspace']) > 120 || $this->hasControls($data['workspace'])) {
            $errors[] = 'Workspace / property name is required (maximum 120 characters, no control characters).';
        }
        if (! preg_match('/^[A-Za-z0-9.-]+$/D', $data['db_host'])) {
            $errors[] = 'Database host must be a hostname or IP address, usually localhost.';
        }
        if (! preg_match('/^[0-9]{1,5}$/D', $data['db_port']) || (int) $data['db_port'] < 1 || (int) $data['db_port'] > 65535) {
            $errors[] = 'Database port must be between 1 and 65535, usually 3306.';
        }
        if (! preg_match('/^[A-Za-z0-9_]+$/D', $data['db_name']) || strlen($data['db_name']) > 64) {
            $errors[] = 'Enter the FULL prefixed database name exactly as shown in cPanel (letters, numbers, underscores).';
        }
        if (! preg_match('/^[A-Za-z0-9_.-]+$/D', $data['db_user']) || strlen($data['db_user']) > 80) {
            $errors[] = 'Enter the FULL prefixed database username exactly as shown in cPanel.';
        }
        if ($data['db_password'] === '' || strlen($data['db_password']) > 1024 || $this->hasControls($data['db_password'])) {
            $errors[] = 'Database password is required and must be a single line.';
        }
        if ($data['admin_name'] === '' || $this->length($data['admin_name']) > 120 || $this->hasControls($data['admin_name'])) {
            $errors[] = 'Administrator name is required (maximum 120 characters).';
        }
        if (! filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL) || strlen($data['admin_email']) > 254) {
            $errors[] = 'Enter a valid administrator email address.';
        }
        if (strlen($data['admin_password']) < 12 || strlen($data['admin_password']) > 256 || $this->hasControls($data['admin_password'])) {
            $errors[] = 'Administrator password must be 12–256 characters on a single line.';
        }
        if (! hash_equals($data['admin_password'], $confirm)) {
            $errors[] = 'Administrator password confirmation does not match.';
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /** @return array{ok:bool,errors:string[],warnings:string[],login_url:string,cron:string} */
    public function install(array $input): array
    {
        $result = ['ok' => false, 'errors' => [], 'warnings' => [], 'login_url' => '', 'cron' => ''];
        if ($this->isInstalled()) {
            $result['errors'][] = 'NAi is already installed. The installer cannot be run again.';
            return $result;
        }

        $validated = $this->validate($input);
        $data = $validated['data'];
        $result['errors'] = $validated['errors'];
        if ($result['errors']) return $result;

        foreach ($this->requirements() as $check) {
            if (! $check['ok']) $result['errors'][] = $check['label'].': '.$check['hint'];
        }
        if ($result['errors']) return $result;

        try {
            $pdo = $this->connect($data);
            $existingAdmin = $this->assertDatabaseAllowed($pdo, $data);
            $this->writePending($data);
            $this->writeEnvironment($data);
            $this->runApplicationSetup($data, $existingAdmin);
            $result['warnings'] = $this->complete($data);
            $result['ok'] = true;
            $result['login_url'] = $data['site_url'].'/login';
            $result['cron'] = self::cronCommand($this->root);
        } catch (Throwable $e) {
            $message = $this->redact($e->getMessage(), $data);
            $result['errors'][] = $message !== '' ? (function_exists('mb_substr') ? mb_substr($message, 0, 700) : substr($message, 0, 700)) : 'Installation failed. Check storage/logs/laravel.log and the database privileges.';
        }

        return $result;
    }

    public static function cronCommand(string $root, ?string $phpBinary = null): string
    {
        $php = $phpBinary ?: (PHP_BINARY ?: '/usr/local/bin/php');
        $quote = fn (string $path): string => preg_match('/\s/', $path) ? '"'.str_replace('"', '\\"', $path).'"' : $path;

        return $quote($php).' '.$quote(rtrim($root, '/\\').'/artisan').' schedule:run >> /dev/null 2>&1';
    }

    public static function dotenvValue(string $value): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new RuntimeException('Environment values must be single-line.');
        }
        if ($value === '') return "''";
        if (preg_match('/^[A-Za-z0-9_.\/:@-]+$/D', $value)) return $value;
        if (! str_contains($value, "'")) return "'".$value."'";

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    protected function connect(array $data): PDO
    {
        $dsn = 'mysql:host='.$data['db_host'].';port='.$data['db_port'].';dbname='.$data['db_name'].';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, $data['db_user'], $data['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 8,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $selected = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($selected !== $data['db_name']) {
                throw new RuntimeException('The database server selected a different database.');
            }
            return $pdo;
        } catch (Throwable $e) {
            throw new RuntimeException('Database connection failed. Check the FULL prefixed database name, user, password, host, and ALL PRIVILEGES assignment.', 0, $e);
        }
    }

    protected function environment(array $data): string
    {
        $https = str_starts_with(strtolower($data['site_url']), 'https://');
        $values = [
            'APP_NAME' => 'NAi', 'APP_ENV' => 'production', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false', 'APP_URL' => $data['site_url'], 'ASSET_URL' => '/',
            'APP_LOCALE' => 'en', 'APP_FALLBACK_LOCALE' => 'en', 'APP_FAKER_LOCALE' => 'en_US',
            'APP_MAINTENANCE_DRIVER' => 'file', 'BCRYPT_ROUNDS' => '12',
            'LOG_CHANNEL' => 'daily', 'LOG_STACK' => 'single', 'LOG_DEPRECATIONS_CHANNEL' => 'null', 'LOG_LEVEL' => 'warning', 'LOG_DAILY_DAYS' => '14',
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $data['db_host'], 'DB_PORT' => $data['db_port'],
            'DB_DATABASE' => $data['db_name'], 'DB_USERNAME' => $data['db_user'], 'DB_PASSWORD' => $data['db_password'],
            'SESSION_DRIVER' => 'file', 'SESSION_LIFETIME' => '120', 'SESSION_ENCRYPT' => 'false', 'SESSION_PATH' => '/',
            'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => $https ? 'true' : 'false', 'SESSION_SAME_SITE' => 'lax',
            'BROADCAST_CONNECTION' => 'log', 'FILESYSTEM_DISK' => 'local', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'database',
            'MAIL_MAILER' => 'log', 'MAIL_FROM_ADDRESS' => 'hello@nowills.com', 'MAIL_FROM_NAME' => 'NAi',
            'NAI_WORKSPACE_NAME' => $data['workspace'], 'NAI_DEMO_MODE' => 'false', 'NAI_PREVIEW_AUTO_DEMO' => 'false', 'NAI_PREVIEW_LANDING' => 'dashboard',
            'NAI_MONITORING_ENABLED' => 'false', 'NAI_MONITORING_TIMEOUT' => '3', 'NAI_MONITORING_VERIFY_SSL' => 'true', 'NAI_MONITORING_RETENTION_DAYS' => '30',
            'ARENA_PORTAL_URL' => 'https://arena.ai', 'ARENA_BASE_URL' => '', 'ARENA_API_KEY' => '', 'ARENA_WORKSPACE_REF' => 'nowills-group',
            'ARENA_CHAT_PATH' => '/v1/chat', 'ARENA_HEALTH_PATH' => '/v1/health', 'ARENA_TIMEOUT' => '45',
        ];
        $lines = ['# Generated by the NAi cPanel installer. Keep this file private.'];
        foreach ($values as $key => $value) $lines[] = $key.'='.self::dotenvValue($value);

        return implode("\n", $lines)."\n";
    }

    protected function writeEnvironment(array $data): void
    {
        $target = $this->root.'/.env';
        if (is_file($target) && ! is_file($this->pendingPath())) {
            throw new RuntimeException('An .env file already exists. This installer will not overwrite an existing configuration.');
        }
        $this->atomicWrite($target, $this->environment($data), 0600);
    }

    protected function runApplicationSetup(array $data, bool $existingAdmin): void
    {
        foreach (glob($this->root.'/bootstrap/cache/*.php') ?: [] as $cache) @unlink($cache);

        $autoload = is_file($this->root.'/build/vendor/autoload.php') ? $this->root.'/build/vendor/autoload.php' : $this->root.'/vendor/autoload.php';
        require_once $autoload;
        $app = require $this->root.'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $this->artisan('migrate', ['--force' => true]);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder', '--force' => true]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $existingAdmin): void {
            \App\Models\Setting::updateOrCreate(['key' => 'workspace_name'], ['value' => $data['workspace']]);
            \App\Models\ArenaConnection::firstOrCreate([], [
                'chat_path' => config('nai.arena.chat_path'), 'health_path' => config('nai.arena.health_path'), 'status' => 'disconnected',
            ]);
            if (! $existingAdmin) {
                \App\Models\User::create([
                    'name' => $data['admin_name'], 'email' => $data['admin_email'], 'password' => $data['admin_password'],
                    'role' => 'admin', 'title' => 'Platform Administrator',
                ]);
            }
        });

        $this->artisan('config:cache');
        $this->artisan('route:cache');
        $this->artisan('view:cache');
    }

    protected function artisan(string $command, array $args = []): void
    {
        $code = \Illuminate\Support\Facades\Artisan::call($command, $args);
        if ($code !== 0) {
            throw new RuntimeException('Setup command failed: '.$command.'. '.\Illuminate\Support\Facades\Artisan::output());
        }
    }

    protected function complete(array $data): array
    {
        $this->atomicWrite($this->root.'/storage/app/installed.lock', json_encode([
            'application' => 'NAi', 'installer' => self::VERSION, 'installed_at' => gmdate('c'), 'site_url' => $data['site_url'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", 0644);
        @unlink($this->pendingPath());

        $warnings = [];
        foreach (['public/install.php', 'SETUP-TOKEN.txt', 'build/installer-config.php'] as $relative) {
            $path = $this->root.'/'.$relative;
            if (is_file($path) && ! @unlink($path)) {
                $warnings[] = 'Delete '.$relative.' manually in cPanel File Manager; PHP could not remove it automatically.';
            }
        }
        return $warnings;
    }

    private function assertDatabaseAllowed(PDO $pdo, array $data): bool
    {
        $tables = array_map(fn ($row) => (string) $row[0], $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM));
        $pending = $this->readPending();
        if ($pending !== null && ($pending['fingerprint'] ?? '') !== $this->fingerprint($data)) {
            throw new RuntimeException('A different pending installation owns this project. Use the same site, database, and administrator details, or restore an empty project/database before retrying.');
        }
        if ($tables === []) return false;
        if ($pending === null) {
            throw new RuntimeException('The selected database is not empty. Create a new empty database in cPanel; the installer never overwrites existing tables.');
        }
        if (! in_array('users', $tables, true)) return false;

        $rows = $pdo->query('SELECT email, password, role FROM users')->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 1 && strtolower((string) $rows[0]['email']) === $data['admin_email']
            && $rows[0]['role'] === 'admin' && password_verify($data['admin_password'], (string) $rows[0]['password'])) {
            return true;
        }
        throw new RuntimeException('The pending database already contains different users. Installation was stopped without changing them.');
    }

    private function writePending(array $data): void
    {
        $this->atomicWrite($this->pendingPath(), json_encode([
            'installer' => self::VERSION, 'started_at' => gmdate('c'), 'fingerprint' => $this->fingerprint($data),
        ], JSON_PRETTY_PRINT)."\n", 0600);
    }

    private function readPending(): ?array
    {
        if (! is_file($this->pendingPath())) return null;
        $decoded = json_decode((string) file_get_contents($this->pendingPath()), true);
        if (! is_array($decoded)) throw new RuntimeException('The pending installation marker is unreadable. Restore the project from the ZIP and use an empty database.');
        return $decoded;
    }

    private function fingerprint(array $data): string
    {
        return hash('sha256', implode('|', [$data['site_url'], $data['db_host'], $data['db_port'], $data['db_name'], $data['db_user'], $data['admin_email']]));
    }

    private function pendingPath(): string { return $this->root.'/storage/app/installing.lock'; }

    private function config(): array
    {
        $path = $this->root.'/build/installer-config.php';
        if (! is_file($path)) return [];
        $config = require $path;
        return is_array($config) ? $config : [];
    }

    private function hasControls(string $value): bool { return (bool) preg_match('/[\x00-\x1F\x7F]/', $value); }

    private function length(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value); }

    private function redact(string $message, array $data): string
    {
        foreach (['db_password', 'admin_password'] as $key) {
            if (($data[$key] ?? '') !== '') $message = str_replace($data[$key], '[redacted]', $message);
        }
        return $message;
    }

    private function atomicWrite(string $path, string $contents, int $permissions): void
    {
        $temporary = $path.'.tmp-'.bin2hex(random_bytes(6));
        if (! is_dir(dirname($path))) throw new RuntimeException('Missing directory: '.basename(dirname($path)));
        $handle = @fopen($temporary, 'xb');
        if ($handle === false) throw new RuntimeException('Cannot write to '.basename(dirname($path)).'. Check cPanel ownership and permissions.');
        try {
            @chmod($temporary, $permissions);
            if (fwrite($handle, $contents) !== strlen($contents)) throw new RuntimeException('Short write while saving '.basename($path).'.');
            fclose($handle);
            if (! @rename($temporary, $path)) throw new RuntimeException('Cannot finalize '.basename($path).'. Check directory permissions.');
            @chmod($path, $permissions);
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }
}
