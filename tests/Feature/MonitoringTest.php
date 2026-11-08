<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Incident;
use App\Models\ResourceCheck;
use App\Models\User;
use App\Services\MonitoringStats;
use App\Services\ResourceMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        return User::firstOrCreate(['email' => $role.'@test.local'], ['name' => ucfirst($role), 'password' => 'password-123', 'role' => $role]);
    }

    private function makeApplication(string $status = 'unknown', string $url = 'https://probe.example.test'): Application
    {
        return Application::create(['name' => 'Probed App', 'slug' => 'probed-app', 'url' => $url, 'environment' => 'production', 'status' => $status]);
    }

    public function test_successful_probe_records_history_and_updates_status(): void
    {
        config(['nai.monitoring.timeout' => 1]);
        Http::fake(['*' => Http::response('ok', 200)]);
        $app = $this->makeApplication();

        $summary = app(ResourceMonitor::class)->runAll();

        $this->assertSame(1, $summary['checked']);
        $this->assertSame(1, $summary['healthy']);
        $this->assertSame('healthy', $app->fresh()->status);
        $this->assertDatabaseHas('resource_checks', ['resource_type' => 'applications', 'status' => 'healthy', 'source' => 'probe']);
    }

    public function test_failed_probe_opens_incident_and_recovery_resolves_it(): void
    {
        config(['nai.monitoring.timeout' => 1]);
        $app = $this->makeApplication();
        $monitor = app(ResourceMonitor::class);

        Http::fake(['*' => Http::sequence()->push('down', 503)->push('still down', 500)->push('ok', 200)]);
        $summary = $monitor->checkApplication($app);
        $this->assertSame(1, $summary['incidents_opened']);
        $incident = Incident::open()->first();
        $this->assertNotNull($incident);
        $this->assertSame('major', $incident->severity);

        $monitor->checkApplication($app->fresh());
        $this->assertSame(1, Incident::open()->count());
        $this->assertSame(2, Incident::open()->first()->check_count);

        $summary = $monitor->checkApplication($app->fresh());
        $this->assertSame(1, $summary['incidents_resolved']);
        $this->assertSame(0, Incident::open()->count());
        $this->assertSame('recovered', Incident::first()->resolution);
        $this->assertSame(3, ResourceCheck::count());
    }

    public function test_dry_run_writes_no_history_or_incidents(): void
    {
        config(['nai.monitoring.timeout' => 1]);
        Http::fake(['*' => Http::response('down', 503)]);
        $app = $this->makeApplication();

        app(ResourceMonitor::class)->runAll(true);

        $this->assertSame('unknown', $app->fresh()->status);
        $this->assertSame(0, ResourceCheck::count());
        $this->assertSame(0, Incident::count());
    }

    public function test_maintenance_resources_do_not_raise_incidents(): void
    {
        config(['nai.monitoring.timeout' => 1]);
        Http::fake(['*' => Http::response('down', 503)]);
        $app = $this->makeApplication('maintenance');

        app(ResourceMonitor::class)->checkApplication($app);

        $this->assertSame(0, Incident::count());
        $this->assertSame(1, ResourceCheck::count());
    }

    public function test_uptime_and_latency_are_aggregated_from_real_checks(): void
    {
        $app = $this->makeApplication('healthy');
        foreach (['healthy', 'healthy', 'healthy', 'offline'] as $i => $status) {
            ResourceCheck::create([
                'resource_type' => 'applications', 'resource_id' => $app->id, 'resource_name' => $app->name,
                'status' => $status, 'latency_ms' => $status === 'offline' ? null : 100, 'source' => 'probe',
                'checked_at' => now()->subMinutes(10 * (4 - $i)),
            ]);
        }

        $stats = app(MonitoringStats::class);
        $row = collect($stats->board())->firstWhere('id', $app->id);
        $this->assertSame(75.0, $row['uptime']);
        $this->assertSame(4, $row['checks']);
        $this->assertSame(100, $row['latency']);
        $this->assertCount(4, $row['spark']);
        $this->assertSame(75.0, $stats->summary()['uptime']);
    }

    public function test_monitoring_page_renders_but_viewers_cannot_run_checks(): void
    {
        $this->actingAs($this->user('viewer'))->get('/monitoring')->assertOk()->assertSee('Resource health board');
        $this->actingAs($this->user('viewer'))->post('/monitoring/run')->assertForbidden();
    }

    public function test_operator_can_manually_resolve_an_incident_with_a_note(): void
    {
        $operator = $this->user('operator');
        $app = $this->makeApplication('offline');
        $incident = Incident::create([
            'resource_type' => 'applications', 'resource_id' => $app->id, 'resource_name' => $app->name,
            'title' => 'Probed App is offline', 'severity' => 'critical', 'status' => 'open', 'trigger_status' => 'offline',
            'check_count' => 2, 'started_at' => now()->subMinutes(20), 'last_seen_at' => now(),
        ]);

        $this->actingAs($operator)->post('/monitoring/incidents/'.$incident->id.'/resolve', ['note' => 'Failover completed'])
            ->assertRedirect()->assertSessionHas('success');

        $fresh = $incident->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertSame('manual', $fresh->resolution);
        $this->assertSame($operator->id, $fresh->resolved_by);
        $this->assertSame('Failover completed', $fresh->resolution_note);
        $this->assertDatabaseHas('activities', ['action' => 'monitoring.incident_resolved']);
    }

    public function test_prune_removes_old_history_but_keeps_open_incidents(): void
    {
        config(['nai.monitoring.retention_days' => 7]);
        $app = $this->makeApplication('offline');
        ResourceCheck::create(['resource_type' => 'applications', 'resource_id' => $app->id, 'resource_name' => $app->name, 'status' => 'healthy', 'source' => 'probe', 'checked_at' => now()->subDays(30)]);
        ResourceCheck::create(['resource_type' => 'applications', 'resource_id' => $app->id, 'resource_name' => $app->name, 'status' => 'healthy', 'source' => 'probe', 'checked_at' => now()->subDay()]);
        $base = ['resource_type' => 'applications', 'resource_id' => $app->id, 'resource_name' => $app->name, 'title' => 't', 'severity' => 'major', 'trigger_status' => 'offline', 'check_count' => 1, 'last_seen_at' => now()];
        Incident::create($base + ['status' => 'open', 'started_at' => now()->subDays(40)]);
        Incident::create($base + ['status' => 'resolved', 'started_at' => now()->subDays(40), 'resolved_at' => now()->subDays(39), 'resolution' => 'recovered']);

        $deleted = app(ResourceMonitor::class)->prune();

        $this->assertSame(1, $deleted);
        $this->assertSame(1, ResourceCheck::count());
        $this->assertSame(1, Incident::count());
        $this->assertSame('open', Incident::first()->status);
    }

    public function test_monitoring_export_streams_check_history(): void
    {
        $app = $this->makeApplication('healthy');
        ResourceCheck::create(['resource_type' => 'applications', 'resource_id' => $app->id, 'resource_name' => '=HYPERLINK("x")', 'status' => 'healthy', 'source' => 'probe', 'checked_at' => now()]);

        $response = $this->actingAs($this->user())->get('/monitoring/export');
        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Checked at', $content);
        $this->assertStringContainsString('Resource type', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_workspace_export_includes_monitoring_posture_without_secrets(): void
    {
        \App\Models\ArenaConnection::create(['base_url' => 'https://arena.example.test', 'api_key' => 'super-secret', 'status' => 'disconnected']);
        $app = $this->makeApplication('offline');
        Incident::create(['resource_type' => 'applications', 'resource_id' => $app->id, 'resource_name' => $app->name, 'title' => 'down', 'severity' => 'critical', 'status' => 'open', 'trigger_status' => 'offline', 'check_count' => 1, 'started_at' => now(), 'last_seen_at' => now()]);

        $content = $this->actingAs($this->user())->get('/settings/export')->streamedContent();
        $this->assertStringContainsString('"monitoring"', $content);
        $this->assertStringContainsString('open_incidents', $content);
        $this->assertStringNotContainsString('super-secret', $content);
    }
}
