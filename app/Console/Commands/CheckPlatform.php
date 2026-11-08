<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckPlatform extends Command
{
    protected $signature = 'nai:check';
    protected $description = 'Verify runtime, database, and Arena AI configuration';

    public function handle(): int
    {
        $ok = true;
        $this->line('PHP '.PHP_VERSION);
        foreach (['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo'] as $ext) {
            $loaded = extension_loaded($ext); $this->line(($loaded ? '✓' : '✗').' ext-'.$ext); $ok = $ok && $loaded;
        }
        $mysql = extension_loaded('pdo_mysql');
        $this->line(($mysql ? '✓' : '✗').' ext-pdo_mysql (required for MySQL production)');
        $ok = $ok && $mysql;
        try { DB::connection()->getPdo(); $this->line('✓ Database connection: '.DB::connection()->getDriverName()); }
        catch (\Throwable $e) { $this->error('✗ Database connection failed.'); $ok = false; }
        $this->line((config('nai.arena.base_url') ? '✓' : '○').' Arena AI endpoint '.(config('nai.arena.base_url') ? 'configured' : 'not configured (interface-only mode)'));
        $this->line((config('nai.demo_mode') ? '!' : '✓').' Demo mode '.(config('nai.demo_mode') ? 'enabled — disable in production' : 'disabled'));
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
