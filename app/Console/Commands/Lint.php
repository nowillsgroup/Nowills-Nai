<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class Lint extends Command
{
    protected $signature = 'nai:lint';
    protected $description = 'Syntax-check all application PHP files';

    public function handle(): int
    {
        $finder = Finder::create()->in([app_path(), database_path(), routes::class ? base_path('routes') : base_path('routes')])->name('*.php');
        $files = 0; $failed = 0;
        foreach ($finder as $file) {
            $files++;
            exec(PHP_BINARY.' -l '.escapeshellarg($file->getRealPath()).' 2>&1', $out, $code);
            if ($code !== 0) { $failed++; $this->error(implode("\n", $out)); }
            $out = [];
        }
        $this->info("Checked {$files} PHP files; {$failed} syntax errors.");
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
