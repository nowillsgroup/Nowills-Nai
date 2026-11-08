<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;

class Lint extends Command
{
    protected $signature = 'nai:lint {--path=* : Additional directories to check} {--skip-blade : Only check plain PHP files}';

    protected $description = 'Syntax-check application PHP files and compile every Blade template';

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
            if (! $this->checkFile($file->getRealPath(), $file->getRelativePathname())) $failed++;
        }
        $this->info("Checked {$files} PHP files; {$failed} syntax errors.");

        if ($this->option('skip-blade')) return $failed ? self::FAILURE : self::SUCCESS;

        $views = 0;
        $bladeFailed = 0;
        $temp = null;
        foreach (Finder::create()->in(resource_path('views'))->name('*.blade.php')->sortByName() as $file) {
            $views++;
            try {
                $compiled = Blade::compileString($file->getContents());
            } catch (\Throwable $e) {
                $bladeFailed++;
                $this->error($file->getRelativePathname().': Blade compile failed — '.$e->getMessage());
                continue;
            }
            $temp = tempnam(sys_get_temp_dir(), 'nai-blade-');
            file_put_contents($temp, $compiled);
            if (! $this->checkFile($temp, $file->getRelativePathname().' (compiled)')) $bladeFailed++;
            @unlink($temp);
        }
        $this->info("Compiled {$views} Blade templates; {$bladeFailed} errors.");

        return ($failed || $bladeFailed) ? self::FAILURE : self::SUCCESS;
    }

    private function checkFile(string $path, string $label): bool
    {
        $out = [];
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $out, $code);
        if ($code !== 0) $this->error($label.': '.implode("\n", $out));

        return $code === 0;
    }
}
