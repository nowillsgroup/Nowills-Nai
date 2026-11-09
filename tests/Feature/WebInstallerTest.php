<?php

namespace Tests\Feature;

use App\Support\WebInstaller;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WebInstallerTest extends TestCase
{
    private string $root;
    private string $token = '0123456789abcdef0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/nai-installer-test-'.bin2hex(random_bytes(6));
        foreach (['storage/app', 'bootstrap/cache', 'public', 'build'] as $directory) mkdir($this->root.'/'.$directory, 0755, true);
        file_put_contents($this->root.'/build/installer-config.php', '<?php return '.var_export(['setup_token_hash' => hash('sha256', $this->token)], true).';');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($this->root);
        parent::tearDown();
    }

    public function test_valid_setup_input_is_normalized(): void
    {
        $result = (new WebInstaller($this->root))->validate($this->input());
        $this->assertSame([], $result['errors']);
        $this->assertSame('https://nai.nowills.com', $result['data']['site_url']);
        $this->assertSame('admin@nowills.com', $result['data']['admin_email']);
    }

    public function test_wrong_token_weak_password_and_invalid_url_fail_without_reflecting_secrets(): void
    {
        $input = $this->input(['setup_token' => str_repeat('f', 48), 'admin_password' => 'short', 'admin_password_confirm' => 'different', 'site_url' => 'https://user:pass@nai.nowills.com/path?x=1']);
        $result = (new WebInstaller($this->root))->validate($input);
        $this->assertNotEmpty($result['errors']);
        $text = implode(' ', $result['errors']);
        $this->assertStringNotContainsString('short', $text);
        $this->assertStringNotContainsString('pass@', $text);
        $this->assertStringContainsString('setup token is invalid', $text);
    }

    public function test_dotenv_round_trips_literal_passwords_and_generates_a_real_key(): void
    {
        $password = 'a$#b\\c"d\'e ${APP_NAME} f=+/:';
        $probe = new class($this->root) extends WebInstaller {
            public function content(array $data): string { return $this->environment($data); }
        };
        $validated = (new WebInstaller($this->root))->validate($this->input(['db_password' => $password]));
        $content = $probe->content($validated['data']);
        $parsed = Dotenv::parse($content);
        $this->assertSame($password, $parsed['DB_PASSWORD']);
        $this->assertSame('Nowills Group', $parsed['NAI_WORKSPACE_NAME']);
        $this->assertSame('production', $parsed['APP_ENV']);
        $this->assertSame('false', $parsed['NAI_DEMO_MODE']);
        $this->assertSame(32, strlen(base64_decode(substr($parsed['APP_KEY'], 7), true)));
        $this->expectException(RuntimeException::class);
        WebInstaller::dotenvValue("line\nbreak");
    }

    public function test_environment_is_written_privately_and_existing_env_is_not_overwritten(): void
    {
        $probe = new class($this->root) extends WebInstaller {
            public function write(array $data): void { $this->writeEnvironment($data); }
        };
        $data = (new WebInstaller($this->root))->validate($this->input())['data'];
        $probe->write($data);
        $this->assertSame(0600, fileperms($this->root.'/.env') & 0777);
        $this->assertStringContainsString('DB_DATABASE=myacct_nai', file_get_contents($this->root.'/.env'));

        file_put_contents($this->root.'/.env', 'SENTINEL=keep');
        try {
            $probe->write($data);
            $this->fail('Existing .env was overwritten without a pending installation marker.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }
        $this->assertSame('SENTINEL=keep', file_get_contents($this->root.'/.env'));
    }

    public function test_completion_locks_installation_and_removes_installer_material(): void
    {
        file_put_contents($this->root.'/install.php', '<?php // root launcher');
        file_put_contents($this->root.'/public/install.php', '<?php // installer');
        file_put_contents($this->root.'/SETUP-TOKEN.txt', $this->token);
        $probe = new class($this->root) extends WebInstaller {
            public function finish(array $data): array { return $this->complete($data); }
        };
        $data = (new WebInstaller($this->root))->validate($this->input())['data'];
        $this->assertSame([], $probe->finish($data));
        $this->assertFileDoesNotExist($this->root.'/install.php');
        $this->assertFileDoesNotExist($this->root.'/public/install.php');
        $this->assertFileDoesNotExist($this->root.'/SETUP-TOKEN.txt');
        $this->assertFileDoesNotExist($this->root.'/build/installer-config.php');
        $this->assertFileExists($this->root.'/storage/app/installed.lock');
        $result = (new WebInstaller($this->root))->install($this->input());
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('already installed', $result['errors'][0]);
    }

    public function test_missing_bundled_vendor_fails_closed_before_database_access(): void
    {
        $result = (new WebInstaller($this->root))->install($this->input());
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Bundled application packages', implode(' ', $result['errors']));
        $this->assertFileDoesNotExist($this->root.'/.env');
    }

    public function test_root_fallback_forwards_to_public_and_denies_private_paths(): void
    {
        $htaccess = file_get_contents(__DIR__.'/../../deploy/cpanel-package/.htaccess');
        $this->assertStringContainsString('RewriteRule ^(.*)$ public/$1 [L]', $htaccess);
        $this->assertStringContainsString('RewriteRule ^$ public/index.php [L]', $htaccess);
        $this->assertStringContainsString('storage|tests|vendor', $htaccess);
        $this->assertStringContainsString('SETUP-TOKEN\.txt', $htaccess);
        $this->assertStringContainsString('Require all denied', $htaccess);
    }

    public function test_installer_form_uses_csrf_and_never_reflects_password_fields(): void
    {
        $template = file_get_contents(__DIR__.'/../../deploy/cpanel-package/public/install.php');
        $this->assertStringContainsString("hash_equals((string) \$_SESSION['csrf'], \$csrf)", $template);
        $this->assertStringContainsString('name="db_password" type="password" required autocomplete="new-password"', $template);
        $this->assertStringContainsString('name="admin_password" type="password" required minlength="12"', $template);
        $this->assertStringNotContainsString('name="db_password" type="password" required autocomplete="new-password" value=', $template);
    }

    public function test_cron_command_uses_the_actual_project_path_and_quotes_spaces(): void
    {
        $command = WebInstaller::cronCommand('/home/user/my nai', '/usr/local/bin/php');
        $this->assertSame('/usr/local/bin/php "/home/user/my nai/artisan" schedule:run >> /dev/null 2>&1', $command);
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'setup_token' => $this->token,
            'site_url' => 'https://nai.nowills.com/',
            'workspace' => 'Nowills Group',
            'db_host' => 'localhost',
            'db_port' => '3306',
            'db_name' => 'myacct_nai',
            'db_user' => 'myacct_naiuser',
            'db_password' => 'Strong-DB-Password-123!',
            'admin_name' => 'NAi Administrator',
            'admin_email' => 'Admin@nowills.com',
            'admin_password' => 'Strong-Admin-Password-123!',
            'admin_password_confirm' => 'Strong-Admin-Password-123!',
        ], $overrides);
    }
}
