<?php

namespace App\Console\Commands;

use App\Services\ResourceMonitor;
use Illuminate\Console\Command;

class MonitorResources extends Command
{
    protected $signature = 'nai:monitor {--dry-run : Check without writing status changes}';
    protected $description = 'Probe application URLs and infrastructure/database endpoints and update health status';

    public function handle(ResourceMonitor $monitor): int
    {
        $summary = $monitor->runAll((bool) $this->option('dry-run'));
        $this->table(['Checked', 'Healthy', 'Degraded', 'Offline', 'Skipped'], [[
            $summary['checked'], $summary['healthy'], $summary['degraded'], $summary['offline'], $summary['skipped'],
        ]]);
        return self::SUCCESS;
    }
}
