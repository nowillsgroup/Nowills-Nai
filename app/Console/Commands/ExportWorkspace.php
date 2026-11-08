<?php

namespace App\Console\Commands;

use App\Services\WorkspaceExporter;
use Illuminate\Console\Command;

class ExportWorkspace extends Command
{
    protected $signature = 'nai:export {path? : Output file path}';
    protected $description = 'Export a versioned operational workspace backup as JSON (excluding secrets)';

    public function handle(WorkspaceExporter $exporter): int
    {
        $path = $this->argument('path') ?: storage_path('app/private/nai-workspace-'.now()->format('Ymd-His').'.json');
        try {
            $json = json_encode($exporter->snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\JsonException) { $this->error('The workspace could not be encoded as JSON.'); return self::FAILURE; }
        if (file_put_contents($path, $json, LOCK_EX) === false) { $this->error('Could not write the backup. Check the destination directory and permissions.'); return self::FAILURE; }
        $this->info('Exported version 1 workspace backup to '.$path);
        return self::SUCCESS;
    }
}
