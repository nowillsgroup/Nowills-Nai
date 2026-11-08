<?php

namespace App\Console\Commands;

use App\Services\ResourceMonitor;
use Illuminate\Console\Command;

class PruneMonitoring extends Command
{
    protected $signature = 'nai:monitor-prune {--days= : Retention window in days (defaults to NAI_MONITORING_RETENTION_DAYS)}';

    protected $description = 'Delete monitoring check history and resolved incidents outside the retention window';

    public function handle(ResourceMonitor $monitor): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $deleted = $monitor->prune($days);
        $this->info(sprintf('Pruned %d monitoring check(s).', $deleted));

        return self::SUCCESS;
    }
}
