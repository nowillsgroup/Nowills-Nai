<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\DatabaseConnection;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\ResourceCheck;
use App\Support\Resources;
use Illuminate\Database\Seeder;

/**
 * Sample monitoring history for the demo workspace only.
 * Every row is written with source=demo so the UI can label it as sample data.
 * Idempotent: skips resources that already have check history.
 */
class MonitoringDemoSeeder extends Seeder
{
    private const POINTS = 48;

    public function run(): void
    {
        $groups = [
            'applications' => Application::all(),
            'databases' => DatabaseConnection::all(),
            'infrastructure' => Infrastructure::all(),
        ];

        foreach ($groups as $type => $resources) {
            foreach ($resources as $resource) {
                if (ResourceCheck::for($type, $resource->id)->exists()) continue;
                $target = Resources::target($resource);
                if (! $target) continue;
                if ($type === 'infrastructure' && in_array($resource->type, ['storage', 'network'], true)) continue;

                $pattern = $this->pattern($resource->status);
                $base = $type === 'applications' ? 140 : ($type === 'databases' ? 24 : 38);
                mt_srand(crc32($type.$resource->name));
                $rows = [];
                $failStart = null;
                $failCount = 0;
                $lastStatus = 'healthy';

                for ($i = self::POINTS - 1; $i >= 0; $i--) {
                    $at = now()->subMinutes($i * 30)->setSeconds(0);
                    $status = $pattern[$i] ?? 'healthy';
                    $lastStatus = $status;
                    $latency = $status === 'offline' ? null : $base + mt_rand(-18, 90) + ($status === 'degraded' ? 640 : 0);
                    $rows[] = [
                        'resource_type' => $type, 'resource_id' => $resource->id, 'resource_name' => $resource->name,
                        'status' => $status, 'latency_ms' => $latency,
                        'http_code' => $type === 'applications' ? ($status === 'offline' ? null : ($status === 'degraded' ? 503 : 200)) : null,
                        'target' => $target, 'source' => 'demo', 'checked_at' => $at,
                        'message' => match ($status) {
                            'healthy' => $type === 'applications' ? 'Endpoint returned HTTP 200' : 'Endpoint reachable',
                            'degraded' => $type === 'applications' ? 'Endpoint returned HTTP 503' : 'Endpoint responded slowly',
                            default => 'No response from endpoint within the probe timeout',
                        },
                    ];
                    if ($status !== 'healthy') { $failCount++; $failStart ??= $at; } else { $failStart = null; $failCount = 0; }
                }
                foreach ($rows as $row) ResourceCheck::create($row);

                // Open incident matching a currently unhealthy resource.
                if (in_array($lastStatus, ['degraded', 'offline'], true) && $failStart && $resource->status !== 'maintenance') {
                    Incident::firstOrCreate(
                        ['resource_type' => $type, 'resource_id' => $resource->id, 'status' => 'open'],
                        [
                            'resource_name' => $resource->name, 'title' => $resource->name.' is '.$lastStatus,
                            'severity' => $lastStatus === 'offline' ? 'critical' : 'major', 'trigger_status' => $lastStatus,
                            'detail' => $lastStatus === 'offline' ? 'No response from endpoint within the probe timeout' : 'Endpoint returned an error response',
                            'check_count' => max(1, $failCount), 'started_at' => $failStart, 'last_seen_at' => now()->setSeconds(0), 'source' => 'demo',
                        ]
                    );
                }

                // One resolved incident in the history of every second healthy resource.
                if ($lastStatus === 'healthy' && $resource->id % 2 === 0) {
                    $started = now()->subHours(9);
                    Incident::firstOrCreate(
                        ['resource_type' => $type, 'resource_id' => $resource->id, 'status' => 'resolved', 'started_at' => $started],
                        [
                            'resource_name' => $resource->name, 'title' => $resource->name.' was degraded',
                            'severity' => 'major', 'trigger_status' => 'degraded', 'detail' => 'Elevated response times observed during a deployment window',
                            'check_count' => 3, 'last_seen_at' => $started->copy()->addMinutes(90), 'resolved_at' => $started->copy()->addMinutes(90),
                            'resolution' => 'recovered', 'source' => 'demo',
                        ]
                    );
                }
            }
        }
        mt_srand();
    }

    /** Index 0 = newest check, 47 = oldest. */
    private function pattern(string $status): array
    {
        $pattern = array_fill(0, self::POINTS, 'healthy');
        if ($status === 'degraded') {
            foreach (range(0, 5) as $i) $pattern[$i] = 'degraded';
            $pattern[22] = 'degraded';
        } elseif ($status === 'offline') {
            foreach (range(0, 3) as $i) $pattern[$i] = 'offline';
        } elseif ($status === 'unknown') {
            $pattern[14] = 'degraded';
        }

        return $pattern;
    }
}
