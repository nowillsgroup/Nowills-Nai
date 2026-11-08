<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DatabaseConnection;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\ResourceCheck;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * Probes registered endpoints, records check history, and opens/resolves
 * incidents on state transitions. Dry runs never write to the database.
 */
class ResourceMonitor
{
    public function runAll(bool $dryRun = false): array
    {
        $summary = $this->emptySummary();
        foreach (Application::all() as $item) $this->merge($summary, $this->checkApplication($item, $dryRun));
        foreach (DatabaseConnection::all() as $item) $this->merge($summary, $this->checkDatabase($item, $dryRun));
        foreach (Infrastructure::all() as $item) $this->merge($summary, $this->checkInfrastructure($item, $dryRun));

        if (! $dryRun) {
            Audit::log('monitoring.run', sprintf(
                'Ran health checks: %d checked, %d healthy, %d degraded, %d offline, %d incidents opened, %d resolved',
                $summary['checked'], $summary['healthy'], $summary['degraded'], $summary['offline'], $summary['incidents_opened'], $summary['incidents_resolved']
            ));
        }

        return $summary;
    }

    public function checkApplication(Application $app, bool $dryRun = false): array
    {
        if (! $app->url) return $this->skipped();

        $started = microtime(true);
        try {
            $response = Http::timeout($this->timeout())
                ->withOptions(['verify' => (bool) config('nai.monitoring.verify_ssl', true), 'allow_redirects' => true])
                ->get($app->url);
            $healthy = $response->successful();
            $result = [
                'status' => $healthy ? 'healthy' : 'degraded',
                'http_code' => $response->status(),
                'message' => $healthy ? 'Endpoint returned HTTP '.$response->status() : 'Endpoint returned HTTP '.$response->status(),
            ];
            $attributes = ['status' => $healthy ? 'healthy' : 'degraded', 'health_score' => $healthy ? 100 : 55, 'last_checked_at' => now()];
        } catch (\Throwable $e) {
            $result = ['status' => 'offline', 'http_code' => null, 'message' => $this->clean($e->getMessage())];
            $attributes = ['status' => 'offline', 'health_score' => 0, 'last_checked_at' => now()];
        }
        $result['latency_ms'] = $this->elapsed($started);
        $result['target'] = $app->url;

        return $this->record('applications', $app, $result, $attributes, $dryRun);
    }

    public function checkDatabase(DatabaseConnection $db, bool $dryRun = false): array
    {
        $port = $db->port ?: match ($db->engine) {
            'postgresql' => 5432, 'sqlserver' => 1433, 'mongodb' => 27017, 'redis' => 6379, default => 3306,
        };
        $started = microtime(true);
        $reachable = $this->tcpReachable($db->host, $port, $error);
        $result = [
            'status' => $reachable ? 'healthy' : 'offline',
            'http_code' => null,
            'latency_ms' => $this->elapsed($started),
            'target' => $db->host.':'.$port,
            'message' => $reachable ? 'TCP handshake succeeded on port '.$port : 'TCP connection failed on port '.$port.($error ? ': '.$this->clean($error) : ''),
        ];

        return $this->record('databases', $db, $result, ['status' => $reachable ? 'online' : 'offline'], $dryRun);
    }

    public function checkInfrastructure(Infrastructure $node, bool $dryRun = false): array
    {
        $host = $node->hostname ?: $node->ip_address;
        if (! $host || in_array($node->type, ['storage', 'network'])) return $this->skipped();

        $started = microtime(true);
        $port = 443;
        $reachable = $this->tcpReachable($host, 443, $error);
        if (! $reachable) {
            $port = 80;
            $reachable = $this->tcpReachable($host, 80, $error);
        }
        $result = [
            'status' => $reachable ? 'healthy' : 'offline',
            'http_code' => null,
            'latency_ms' => $this->elapsed($started),
            'target' => $host,
            'message' => $reachable ? 'Reachable on port '.$port : 'No response on ports 443 or 80'.($error ? ': '.$this->clean($error) : ''),
        ];

        return $this->record('infrastructure', $node, $result, ['status' => $reachable ? 'online' : 'offline'], $dryRun);
    }

    /** Delete check history outside the retention window. */
    public function prune(?int $days = null): int
    {
        $days = max(1, $days ?? (int) config('nai.monitoring.retention_days', 30));
        $cutoff = now()->subDays($days);
        $deleted = ResourceCheck::where('checked_at', '<', $cutoff)->delete();
        Incident::where('status', 'resolved')->where('resolved_at', '<', $cutoff)->delete();
        \App\Models\Notification::where('created_at', '<', $cutoff)->delete();
        if ($deleted) Audit::log('monitoring.prune', sprintf('Pruned %d monitoring checks older than %d days', $deleted, $days));

        return $deleted;
    }

    private function record(string $type, Model $resource, array $result, array $attributes, bool $dryRun): array
    {
        $bucket = $this->bucket($result['status']);
        if ($dryRun) return $bucket;

        $maintenance = $resource->status === 'maintenance';
        $resource->update($attributes);

        ResourceCheck::create([
            'resource_type' => $type,
            'resource_id' => $resource->id,
            'resource_name' => $resource->name,
            'status' => $result['status'],
            'latency_ms' => $result['latency_ms'] ?? null,
            'http_code' => $result['http_code'] ?? null,
            'target' => isset($result['target']) ? mb_substr((string) $result['target'], 0, 500) : null,
            'message' => isset($result['message']) ? mb_substr((string) $result['message'], 0, 500) : null,
            'source' => 'probe',
            'checked_at' => now(),
        ]);

        // Resources deliberately placed in maintenance do not raise incidents.
        if (! $maintenance) $this->merge($bucket, $this->syncIncident($type, $resource, $result));

        return $bucket;
    }

    private function syncIncident(string $type, Model $resource, array $result): array
    {
        $counts = ['incidents_opened' => 0, 'incidents_resolved' => 0];
        $open = Incident::open()->forResource($type, $resource->id)->latest('started_at')->first();
        $unhealthy = in_array($result['status'], ['degraded', 'offline'], true);

        if ($unhealthy) {
            $severity = $result['status'] === 'offline' ? 'critical' : 'major';
            if ($open) {
                $open->increment('check_count');
                $open->update([
                    'last_seen_at' => now(), 'severity' => $severity, 'trigger_status' => $result['status'],
                    'detail' => isset($result['message']) ? mb_substr((string) $result['message'], 0, 500) : null,
                ]);

                return $counts;
            }
            $incident = Incident::create([
                'resource_type' => $type, 'resource_id' => $resource->id, 'resource_name' => $resource->name,
                'title' => $resource->name.' is '.$result['status'], 'severity' => $severity, 'status' => 'open',
                'trigger_status' => $result['status'], 'detail' => isset($result['message']) ? mb_substr((string) $result['message'], 0, 500) : null,
                'check_count' => 1, 'started_at' => now(), 'last_seen_at' => now(),
            ]);
            Audit::log('monitoring.incident_opened', sprintf('Incident opened: %s (%s)', $resource->name, $result['status']));
            Notifier::roles(['admin', 'operator'], 'incident.opened', $incident->title, ucfirst($severity).' incident opened by a health check.', route('monitoring.index', [], false), $incident);
            $counts['incidents_opened'] = 1;

            return $counts;
        }

        if ($open) {
            $open->update(['status' => 'resolved', 'resolved_at' => now(), 'resolution' => 'recovered', 'last_seen_at' => now()]);
            Audit::log('monitoring.incident_resolved', sprintf('Incident auto-resolved: %s recovered after %s', $resource->name, $open->durationForHumans()));
            Notifier::roles(['admin', 'operator'], 'incident.resolved', $resource->name.' recovered', 'Incident auto-resolved after '.$open->durationForHumans().'.', route('monitoring.index', [], false), $open);
            $counts['incidents_resolved'] = 1;
        }

        return $counts;
    }

    private function tcpReachable(string $host, int $port, ?string &$error = null): bool
    {
        $errno = 0; $errstr = '';
        $conn = @fsockopen($host, $port, $errno, $errstr, $this->timeout());
        if (is_resource($conn)) { fclose($conn); $error = null; return true; }
        $error = $errstr ?: 'connection refused';

        return false;
    }

    private function timeout(): int
    {
        return max(1, (int) config('nai.monitoring.timeout', 3));
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function clean(string $message): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $message) ?? ''), 0, 400);
    }

    private function emptySummary(): array
    {
        return ['checked' => 0, 'healthy' => 0, 'degraded' => 0, 'offline' => 0, 'skipped' => 0, 'incidents_opened' => 0, 'incidents_resolved' => 0];
    }

    private function skipped(): array
    {
        return ['checked' => 0, 'healthy' => 0, 'degraded' => 0, 'offline' => 0, 'skipped' => 1, 'incidents_opened' => 0, 'incidents_resolved' => 0];
    }

    private function bucket(string $status): array
    {
        return [
            'checked' => 1,
            'healthy' => $status === 'healthy' ? 1 : 0,
            'degraded' => $status === 'degraded' ? 1 : 0,
            'offline' => $status === 'offline' ? 1 : 0,
            'skipped' => 0, 'incidents_opened' => 0, 'incidents_resolved' => 0,
        ];
    }

    private function merge(array &$summary, array $result): void
    {
        foreach ($result as $key => $value) $summary[$key] = ($summary[$key] ?? 0) + $value;
    }
}
