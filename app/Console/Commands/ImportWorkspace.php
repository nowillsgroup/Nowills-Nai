<?php

namespace App\Console\Commands;

use App\Services\WorkspaceImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportWorkspace extends Command
{
    protected $signature = 'nai:import {path : Path to a versioned NAi JSON export} {--dry-run : Validate and show the change plan without writing (default)} {--apply : Explicitly apply the reviewed additions}';
    protected $description = 'Preview or additively import a NAi workspace snapshot; never overwrites or deletes';

    public function handle(WorkspaceImporter $importer): int
    {
        if ($this->option('dry-run') && $this->option('apply')) { $this->error('Choose either --dry-run or --apply, not both.'); return self::FAILURE; }
        $path = $this->argument('path');
        if (!is_file($path) || !is_readable($path)) { $this->error('The snapshot does not exist or is not readable.'); return self::FAILURE; }
        if (filesize($path) > WorkspaceImporter::MAX_BYTES) { $this->error('The snapshot exceeds the 5 MB import limit.'); return self::FAILURE; }
        $plan = $importer->preview(file_get_contents($path), basename($path));
        $this->info('NAi import review · '.$plan['meta']['workspace']);
        $this->table(['Section', 'Create', 'Keep existing', 'Conflicts'], collect($plan['sections'])->map(fn ($s, $type) => [$s['label'], $s['new'], $s['existing'], $s['conflicts']])->values()->all());
        foreach ($plan['warnings'] as $warning) $this->line('Note: '.$warning);
        foreach ($plan['errors'] as $error) $this->error($error['field'].': '.$error['message']);
        if ($plan['error_count'] > count($plan['errors'])) $this->error(($plan['error_count'] - count($plan['errors'])).' additional validation errors omitted.');
        if (!$plan['can_apply']) {
            $this->warn($plan['totals']['conflicts'] ? 'Import blocked: resolve conflicts before trying again.' : ($plan['error_count'] ? 'Import blocked: fix the validation errors.' : 'Nothing to create; all supported records already exist.'));
            return self::FAILURE;
        }
        if (!$this->option('apply')) { $this->info('Dry run complete. No records were written. Re-run with --apply to create the '.$plan['totals']['new'].' missing records.'); return self::SUCCESS; }
        try { $counts = $importer->apply($plan); }
        catch (RuntimeException $e) { $this->error($e->getMessage()); return self::FAILURE; }
        $this->info('Import complete. Created '.array_sum($counts).' records; existing records were left unchanged.');
        return self::SUCCESS;
    }
}
