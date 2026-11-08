<?php

namespace App\Console\Commands;

use App\Services\WorkspaceExporter;
use Illuminate\Console\Command;

class ExportWorkspace extends Command
{
    protected $signature = 'nai:export {path? : Output file path}';
    protected $description = 'Export an operational workspace backup as JSON (excluding secrets)';

    public function handle(WorkspaceExporter $exporter): int
    {
        $path = $this->argument('path') ?: storage_path('app/nai-workspace-'.now()->format('Ymd-His').'.json');
        file_put_contents($path, json_encode($exporter->snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info('Exported workspace to '.$path);
        return self::SUCCESS;
    }
}
