<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class Lint extends Command
{
    protected $signature = 'nai:lint {--path=* : Additional directories to check}';

    protected $description = 'Syntax-check application, configuration, route, database, and test PHP files';

    public function handle(): int
    {
        $paths = array_values(array_filter([
            app_path(), base_path('bootstrap'), config_path(), database_path(), base_path('routes'), base_path('tests'),
            ...((array) $this->option('path')),
        ], fn ($path) => is_dir($path)));

        $files = 0;
        $failed = 0;
        foreach (Finder::create()->in($paths)->name('*.php')->sortByName() as $file) {
            $files++;
            $out = [];
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getRealPath()).' 2>&1', $out, $code);
            if ($code !== 0) {
                $failed++;
                $this->error($file->getRelativePathname().': '.implode("\n", $out));
            }
        }

        $this->info("Checked {$files} PHP files; {$failed} syntax errors.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
