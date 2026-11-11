<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Application;
use App\Models\ArenaConnection;
use App\Models\DatabaseConnection;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Setting::updateOrCreate(['key' => 'workspace_name'], ['value' => 'Nowills Group']);
        ArenaConnection::firstOrCreate([], ['provider' => 'openai', 'model' => \App\Services\ArenaGateway::DEFAULT_OPENAI_MODEL, 'chat_path' => '/v1/chat', 'health_path' => '/v1/health', 'status' => 'disconnected']);

        $admin = User::firstOrCreate(['email' => 'demo@nowills.group'], ['name' => 'Nowills Admin', 'password' => Hash::make('Nowills-Demo-2026!'), 'role' => 'admin', 'title' => 'Platform Administrator']);
        $ama = User::firstOrCreate(['email' => 'ama@nowills.group'], ['name' => 'Ama Mensah', 'password' => Hash::make(Str::random(32)), 'role' => 'operator', 'title' => 'Engineering Lead']);
        $kofi = User::firstOrCreate(['email' => 'kofi@nowills.group'], ['name' => 'Kofi Boateng', 'password' => Hash::make(Str::random(32)), 'role' => 'operator', 'title' => 'Infrastructure Engineer']);
        $efua = User::firstOrCreate(['email' => 'efua@nowills.group'], ['name' => 'Efua Owusu', 'password' => Hash::make(Str::random(32)), 'role' => 'viewer', 'title' => 'Product Manager']);

        $apps = [
            ['Nowills Customer Portal', 'Primary customer self-service portal for accounts, billing, and support.', 'https://portal.nowills.group', 'production', 'healthy', $ama->id, ['Laravel', 'MySQL', 'Redis'], 99],
            ['Nowills Pay', 'Payment orchestration and reconciliation service for group products.', 'https://pay.nowills.group', 'production', 'healthy', $kofi->id, ['Laravel', 'MySQL', 'Queue'], 98],
            ['Inventory Sync', 'Real-time stock synchronization across warehouses and sales channels.', 'https://sync.nowills.group', 'production', 'degraded', $ama->id, ['PHP', 'MySQL', 'Webhooks'], 82],
            ['Customer Insights', 'Analytics workspace for customer behavior and operational reporting.', 'https://insights.nowills.group', 'staging', 'healthy', $efua->id, ['Laravel', 'PostgreSQL'], 96],
            ['HR Workspace', 'Internal people operations, leave, and document management.', 'https://hr.nowills.group', 'production', 'maintenance', $admin->id, ['Laravel', 'MySQL'], 90],
            ['Logistics API', 'Routing, dispatch, and delivery tracking API for logistics operations.', 'https://api.logistics.nowills.group', 'development', 'unknown', $kofi->id, ['Laravel', 'MySQL', 'Redis'], null],
        ];
        $appModels = [];
        foreach ($apps as [$name, $desc, $url, $env, $status, $owner, $stack, $score]) {
            $appModels[] = Application::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'description' => $desc, 'url' => $url, 'environment' => $env, 'status' => $status, 'owner_id' => $owner, 'tech_stack' => $stack, 'health_score' => $score, 'last_checked_at' => now()->subMinutes(rand(2, 40))]);
        }

        $repos = [
            ['nowillsgroup/customer-portal', 'Customer portal monolith and API.', 'https://github.com/nowillsgroup/customer-portal', 'PHP', 0],
            ['nowillsgroup/nowills-pay', 'Payment orchestration service.', 'https://github.com/nowillsgroup/nowills-pay', 'PHP', 1],
            ['nowillsgroup/inventory-sync', 'Inventory synchronization workers.', 'https://github.com/nowillsgroup/inventory-sync', 'PHP', 2],
            ['nowillsgroup/insights', 'Analytics and reporting workspace.', 'https://github.com/nowillsgroup/insights', 'PHP', 3],
            ['nowillsgroup/hr-workspace', 'People operations platform.', 'https://github.com/nowillsgroup/hr-workspace', 'PHP', 4],
            ['nowillsgroup/logistics-api', 'Logistics and dispatch API.', 'https://github.com/nowillsgroup/logistics-api', 'PHP', 5],
            ['nowillsgroup/nai', 'NAi orchestration interface.', 'https://github.com/nowillsgroup/Nowills-Nai', 'PHP', null],
            ['nowillsgroup/design-system', 'Shared UI tokens and components.', 'https://github.com/nowillsgroup/design-system', 'CSS', null],
        ];
        foreach ($repos as [$name, $desc, $url, $lang, $appIndex]) {
            Repository::firstOrCreate(['name' => $name], ['description' => $desc, 'url' => $url, 'provider' => 'github', 'default_branch' => 'main', 'language' => $lang, 'visibility' => 'private', 'status' => 'active', 'application_id' => $appIndex !== null ? $appModels[$appIndex]->id : null, 'last_synced_at' => now()->subHours(rand(1, 20))]);
        }

        $dbs = [
            ['portal_production', 'mysql', '8.4', 'db-portal.internal.nowills.group', 3306, 'production', 'online', 4260, 'current', 0],
            ['pay_ledger', 'mysql', '8.4', 'db-pay.internal.nowills.group', 3306, 'production', 'online', 8120, 'current', 1],
            ['inventory_sync', 'mysql', '8.0', 'db-inventory.internal.nowills.group', 3306, 'production', 'degraded', 2380, 'due', 2],
            ['insights_warehouse', 'postgresql', '16', 'db-insights.internal.nowills.group', 5432, 'staging', 'online', 15400, 'current', 3],
            ['hr_workspace', 'mysql', '8.4', 'db-hr.internal.nowills.group', 3306, 'production', 'online', 940, 'current', 4],
            ['logistics_dev', 'mysql', '8.4', 'db-logistics.dev.nowills.group', 3306, 'development', 'online', 320, 'not_configured', 5],
        ];
        foreach ($dbs as [$name, $engine, $version, $host, $port, $env, $status, $size, $backup, $appIndex]) {
            DatabaseConnection::firstOrCreate(['name' => $name], ['engine' => $engine, 'version' => $version, 'host' => $host, 'port' => $port, 'environment' => $env, 'status' => $status, 'size_mb' => $size, 'backup_status' => $backup, 'last_backup_at' => $backup === 'current' ? now()->subHours(rand(1, 8)) : null, 'application_id' => $appModels[$appIndex]->id]);
        }

        $infra = [
            ['Production web cluster', 'hosting', 'AWS', 'eu-west-1', 'web-prod.nowills.group', '10.0.1.0', 'production', 'online', '3 × 8 vCPU · 32 GB RAM', 0],
            ['Payments service cluster', 'server', 'Hetzner', 'fsn1', 'pay-prod.nowills.group', '10.0.2.0', 'production', 'online', '2 × 16 vCPU · 64 GB RAM', 1],
            ['Sync worker pool', 'server', 'DigitalOcean', 'ams3', 'sync-workers.nowills.group', '10.0.3.0', 'production', 'degraded', '4 × 4 vCPU · 16 GB RAM', 2],
            ['Staging environment', 'hosting', 'AWS', 'eu-west-1', 'staging.nowills.group', '10.0.4.0', 'staging', 'online', '2 × 4 vCPU · 16 GB RAM', 3],
            ['Object storage', 'storage', 'AWS S3', 'eu-west-1', 's3.eu-west-1.amazonaws.com', null, 'production', 'online', '2.4 TB · versioned', null],
            ['Edge CDN', 'cdn', 'Cloudflare', 'global', 'cdn.nowills.group', null, 'production', 'online', 'Global edge · WAF enabled', null],
        ];
        foreach ($infra as [$name, $type, $provider, $region, $hostname, $ip, $env, $status, $specs, $appIndex]) {
            Infrastructure::firstOrCreate(['name' => $name], ['type' => $type, 'provider' => $provider, 'region' => $region, 'hostname' => $hostname, 'ip_address' => $ip, 'environment' => $env, 'status' => $status, 'specs' => $specs, 'application_id' => $appIndex !== null ? $appModels[$appIndex]->id : null, 'ssl_expires_at' => now()->addDays(rand(20, 120))->toDateString()]);
        }

        $projects = [
            ['Portal 2.0 redesign', 'PORTAL', 'Refresh the customer portal experience and consolidate account workflows.', 'active', 'high', $ama->id, 18],
            ['Payments resilience', 'PAY', 'Improve payment retry, reconciliation, and observability.', 'active', 'urgent', $kofi->id, 12],
            ['Inventory reconciliation', 'INV', 'Resolve stock drift and automate warehouse reconciliation.', 'active', 'high', $ama->id, 9],
            ['NAi rollout', 'NAI', 'Deploy the centralized AI orchestration workspace for Nowills Group.', 'planning', 'medium', $admin->id, 30],
        ];
        foreach ($projects as [$name, $key, $desc, $status, $priority, $owner, $due]) {
            $project = Project::firstOrCreate(['key' => $key], ['name' => $name, 'description' => $desc, 'status' => $status, 'priority' => $priority, 'owner_id' => $owner, 'start_date' => now()->subDays(rand(5, 20))->toDateString(), 'due_date' => now()->addDays($due)->toDateString()]);
            if ($project->tasks()->count() === 0) {
                $tasks = [
                    ['Audit current workflows', 'done', 'high'], ['Define target architecture', 'done', 'medium'], ['Implement core changes', 'in_progress', 'high'], ['Security review', 'review', 'urgent'], ['Stakeholder sign-off', 'backlog', 'medium'], ['Production rollout', 'backlog', 'high'],
                ];
                foreach ($tasks as $i => [$title, $status2, $priority2]) {
                    Task::create(['project_id' => $project->id, 'title' => $title, 'status' => $status2, 'priority' => $priority2, 'assignee_id' => $owner, 'due_date' => now()->addDays($due - $i)->toDateString()]);
                }
            }
        }

        if (Activity::count() === 0) {
            $events = [
                ['inventory.updated', 'Inventory Sync marked as degraded', $ama->id, 18],
                ['project.created', 'Created project NAi rollout', $admin->id, 42],
                ['inventory.created', 'Added Edge CDN to infrastructure', $kofi->id, 75],
                ['task.updated', 'Moved Security review to in review', $ama->id, 110],
                ['inventory.updated', 'Payments service cluster verified online', $kofi->id, 160],
                ['auth.login', 'Signed in to NAi', $efua->id, 220],
                ['inventory.created', 'Added Logistics API to applications', $kofi->id, 300],
            ];
            foreach ($events as [$action, $desc, $uid, $minutes]) Activity::create(['user_id' => $uid, 'action' => $action, 'description' => $desc, 'created_at' => now()->subMinutes($minutes)]);
        }

        $this->call(MonitoringDemoSeeder::class);

        if (\App\Models\Notification::count() === 0) {
            $open = \App\Models\Incident::open()->orderByDesc('started_at')->get();
            foreach ($open as $i => $incident) {
                \App\Support\Notifier::send($admin, 'incident.opened', $incident->title, ucfirst($incident->severity).' incident opened by a health check.', '/monitoring', $incident);
            }
            $resolved = \App\Models\Incident::where('status', 'resolved')->orderByDesc('resolved_at')->first();
            if ($resolved) {
                $n = \App\Support\Notifier::send($admin, 'incident.resolved', $resolved->resource_name.' recovered', 'Incident auto-resolved after '.$resolved->durationForHumans().'.', '/monitoring', $resolved);
                $n->update(['read_at' => now()->subMinutes(30), 'created_at' => now()->subHours(2)]);
            }
            $task = Task::where('assignee_id', $admin->id)->where('status', '!=', 'done')->first();
            if ($task) {
                $n = \App\Support\Notifier::send($admin, 'task.assigned', 'New task: '.$task->title, 'Ama Mensah assigned you a '.$task->priority.'-priority task in '.$task->project->key.'.', '/projects/'.$task->project_id, $task);
                $n->update(['created_at' => now()->subHours(4)]);
            }
            foreach (\App\Models\Notification::where('type', 'incident.opened')->get() as $i => $n) {
                $n->update(['created_at' => now()->subMinutes(12 + $i * 25)]);
            }
        }
    }
}
