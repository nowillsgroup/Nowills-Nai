<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DatabaseConnection;
use App\Models\Infrastructure;
use App\Support\Audit;
use Illuminate\Support\Facades\Http;

class ResourceMonitor
{
    public function runAll(bool $dryRun = false): array
    {
        $summary = ['checked' => 0, 'healthy' => 0, 'degraded' => 0, 'offline' => 0, 'skipped' => 0];
        foreach (Application::whereNotNull('url')->get() as $item) $this->merge($summary, $this->checkApplication($item, $dryRun));
        foreach (DatabaseConnection::all() as $item) $this->merge($summary, $this->checkDatabase($item, $dryRun));
        foreach (Infrastructure::all() as $item) $this->merge($summary, $this->checkInfrastructure($item, $dryRun));
        if (! $dryRun) Audit::log('monitoring.run', sprintf('Ran health checks: %d checked, %d healthy, %d degraded, %d offline', $summary['checked'], $summary['healthy'], $summary['degraded'], $summary['offline']));
        return $summary;
    }

    public function checkApplication(Application $app, bool $dryRun = false): array
    {
        if (! $app->url) return ['skipped' => 1];
        try {
            $response = Http::timeout((int) config('nai.monitoring.timeout', 5))
                ->withOptions(['verify' => (bool) config('nai.monitoring.verify_ssl', true), 'allow_redirects' => true])
                ->get($app->url);
            $status = $response->successful() ? 'healthy' : ($response->serverError() ? 'degraded' : 'degraded');
            $score = $response->successful() ? 100 : 55;
        } catch (\Throwable $e) {
            $status = 'offline'; $score = 0;
        }
        if (! $dryRun) $app->update(['status' => $status, 'health_score' => $score, 'last_checked_at' => now()]);
        return $this->bucket($status);
    }

    public function checkDatabase(DatabaseConnection $db, bool $dryRun = false): array
    {
        $port = $db->port ?: match ($db->engine) { 'postgresql' => 5432, 'sqlserver' => 1433, 'mongodb' => 27017, 'redis' => 6379, default => 3306 };
        $status = $this->tcpReachable($db->host, $port) ? 'online' : 'offline';
        if (! $dryRun) $db->update(['status' => $status]);
        return $this->bucket($status === 'online' ? 'healthy' : 'offline');
    }

    public function checkInfrastructure(Infrastructure $node, bool $dryRun = false): array
    {
        $host = $node->hostname ?: $node->ip_address;
        if (! $host || in_array($node->type, ['storage', 'network'])) return ['skipped' => 1];
        $status = ($this->tcpReachable($host, 443) || $this->tcpReachable($host, 80)) ? 'online' : 'offline';
        if (! $dryRun) $node->update(['status' => $status]);
        return $this->bucket($status === 'online' ? 'healthy' : 'offline');
    }

    private function tcpReachable(string $host, int $port): bool
    {
        $timeout = (int) config('nai.monitoring.timeout', 5);
        $conn = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (is_resource($conn)) { fclose($conn); return true; }
        return false;
    }

    private function bucket(string $status): array
    {
        return ['checked' => 1, 'healthy' => $status === 'healthy' ? 1 : 0, 'degraded' => $status === 'degraded' ? 1 : 0, 'offline' => $status === 'offline' ? 1 : 0, 'skipped' => 0];
    }

    private function merge(array &$summary, array $result): void
    {
        foreach ($result as $key => $value) $summary[$key] = ($summary[$key] ?? 0) + $value;
    }
}
