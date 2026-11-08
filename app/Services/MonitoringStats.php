<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\ResourceCheck;
use App\Support\Resources;
use Illuminate\Support\Collection;

/**
 * Read-side aggregations for the monitoring board. All queries are bounded:
 * one aggregate pass per resource type plus a capped sparkline window.
 */
class MonitoringStats
{
    private const SPARKLINE_LIMIT = 600;
    private const SPARKLINE_POINTS = 24;

    public function board(?string $type = null, ?string $state = null): array
    {
        $types = $type && in_array($type, Resources::MONITORED, true) ? [$type] : Resources::MONITORED;
        $since = now()->subDay();

        $aggregates = [];
        foreach ($types as $key) {
            $aggregates[$key] = ResourceCheck::query()
                ->where('resource_type', $key)
                ->where('checked_at', '>=', $since)
                ->selectRaw('resource_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as up, AVG(latency_ms) as avg_latency', ['healthy'])
                ->groupBy('resource_id')->get()->keyBy('resource_id');
        }

        $latestIds = ResourceCheck::whereIn('resource_type', $types)
            ->selectRaw('MAX(id) as id')->groupBy('resource_type', 'resource_id')->pluck('id');
        $latest = ResourceCheck::whereIn('id', $latestIds)->get()->keyBy(fn ($check) => $check->key());

        $recent = ResourceCheck::whereIn('resource_type', $types)
            ->where('checked_at', '>=', now()->subHours(12))
            ->orderByDesc('id')->limit(self::SPARKLINE_LIMIT)->get()
            ->groupBy(fn ($check) => $check->key());

        $openIncidents = Incident::open()->get()->groupBy(fn ($incident) => $incident->resource_type.':'.$incident->resource_id);

        $rows = [];
        foreach ($types as $key) {
            foreach (Resources::model($key)::orderBy('name')->get() as $resource) {
                $resourceKey = $key.':'.$resource->id;
                $agg = $aggregates[$key]->get($resource->id);
                $checks = ($recent->get($resourceKey) ?? new Collection)->take(self::SPARKLINE_POINTS)->reverse()->values();
                $target = Resources::target($resource);

                $row = [
                    'type' => $key, 'id' => $resource->id, 'name' => $resource->name,
                    'icon' => Resources::icon($key), 'label' => Resources::label($key),
                    'environment' => $resource->environment, 'status' => $resource->status,
                    'target' => $target, 'probeable' => $this->probeable($key, $resource, $target),
                    'latest' => $latest->get($resourceKey),
                    'uptime' => $agg && $agg->total ? round($agg->up / $agg->total * 100, 1) : null,
                    'checks' => $agg ? (int) $agg->total : 0,
                    'latency' => $agg && $agg->avg_latency !== null ? (int) round($agg->avg_latency) : null,
                    'spark' => $checks->map(fn ($check) => ['status' => $check->status, 'at' => $check->checked_at->toIso8601String(), 'latency' => $check->latency_ms])->all(),
                    'incident' => ($openIncidents->get($resourceKey) ?? new Collection)->sortByDesc('started_at')->first(),
                ];
                $row['state'] = $this->state($row);
                if ($state && $row['state'] !== $state) continue;
                $rows[] = $row;
            }
        }

        usort($rows, fn ($a, $b) => [$this->rank($a['state']), $a['name']] <=> [$this->rank($b['state']), $b['name']]);

        return $rows;
    }

    public function summary(): array
    {
        $since = now()->subDay();
        $totals = ResourceCheck::where('checked_at', '>=', $since)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END) as up, AVG(latency_ms) as avg_latency")->first();
        $monitored = 0;
        foreach (Resources::MONITORED as $type) $monitored += Resources::model($type)::count();

        return [
            'monitored' => $monitored,
            'checks' => (int) ($totals->total ?? 0),
            'uptime' => $totals && $totals->total ? round($totals->up / $totals->total * 100, 1) : null,
            'latency' => $totals && $totals->avg_latency !== null ? (int) round($totals->avg_latency) : null,
            'open_incidents' => Incident::open()->count(),
            'critical' => Incident::open()->where('severity', 'critical')->count(),
            'resolved_24h' => Incident::where('status', 'resolved')->where('resolved_at', '>=', $since)->count(),
            'last_run' => ResourceCheck::where('source', 'probe')->latest('checked_at')->value('checked_at'),
            'demo_history' => ResourceCheck::where('source', 'demo')->exists(),
            'retention_days' => (int) config('nai.monitoring.retention_days', 30),
            'scheduled' => (bool) config('nai.monitoring.enabled'),
        ];
    }

    private function probeable(string $type, $resource, ?string $target): bool
    {
        if (! $target) return false;
        if ($type === 'infrastructure' && in_array($resource->type, ['storage', 'network'], true)) return false;

        return true;
    }

    private function state(array $row): string
    {
        if ($row['incident'] || in_array($row['status'], ['degraded', 'offline'], true)) return 'attention';
        if (! $row['latest']) return 'unchecked';

        return 'healthy';
    }

    private function rank(string $state): int
    {
        return match ($state) { 'attention' => 0, 'unchecked' => 1, default => 2 };
    }
}
