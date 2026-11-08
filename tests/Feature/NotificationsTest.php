<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ResourceMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin', string $suffix = ''): User
    {
        return User::create(['name' => ucfirst($role).$suffix, 'email' => $role.$suffix.'@test.local', 'password' => 'password-123', 'role' => $role]);
    }

    public function test_incident_open_notifies_admins_and_operators_but_not_viewers(): void
    {
        config(['nai.monitoring.timeout' => 1]);
        Http::fake(['*' => Http::response('down', 503)]);
        $admin = $this->user('admin');
        $operator = $this->user('operator');
        $viewer = $this->user('viewer');
        $app = Application::create(['name' => 'Probed App', 'slug' => 'probed-app', 'url' => 'https://probe.example.test', 'environment' => 'production', 'status' => 'unknown']);

        app(ResourceMonitor::class)->checkApplication($app);

        $this->assertSame(1, Notification::forUser($admin->id)->where('type', 'incident.opened')->count());
        $this->assertSame(1, Notification::forUser($operator->id)->where('type', 'incident.opened')->count());
        $this->assertSame(0, Notification::forUser($viewer->id)->count());
        $this->assertStringContainsString('Probed App', Notification::forUser($admin->id)->first()->title);
    }

    public function test_recovery_notifies_and_dry_run_never_notifies(): void
    {
        config(['nai.monitoring.timeout' => 1]);
        $admin = $this->user('admin');
        $app = Application::create(['name' => 'Probed App', 'slug' => 'probed-app', 'url' => 'https://probe.example.test', 'environment' => 'production', 'status' => 'unknown']);
        $monitor = app(ResourceMonitor::class);

        // One sequence for all three probes: dry run, real failure, recovery.
        // (Calling Http::fake twice merges stubs, so the first 503 would win.)
        Http::fake(['*' => Http::sequence()->push('down', 503)->push('down', 503)->push('ok', 200)]);
        $monitor->checkApplication($app, true);
        $this->assertSame(0, Notification::count());

        $monitor->checkApplication($app->fresh());
        $monitor->checkApplication($app->fresh());

        $this->assertSame(1, Notification::forUser($admin->id)->where('type', 'incident.opened')->count());
        $this->assertSame(1, Notification::forUser($admin->id)->where('type', 'incident.resolved')->count());
    }

    public function test_task_assignment_notifies_assignee_but_not_actor(): void
    {
        $operator = $this->user('operator');
        $assignee = $this->user('operator', '2');
        $project = Project::create(['name' => 'NAi rollout', 'key' => 'NAI', 'status' => 'active', 'priority' => 'high']);

        $this->actingAs($operator)->post('/projects/'.$project->id.'/tasks', [
            'title' => 'Ship notifications', 'status' => 'backlog', 'priority' => 'high', 'assignee_id' => $assignee->id,
        ])->assertRedirect();

        $this->assertSame(1, Notification::forUser($assignee->id)->where('type', 'task.assigned')->count());
        $this->assertSame(0, Notification::forUser($operator->id)->count());

        $this->actingAs($operator)->post('/projects/'.$project->id.'/tasks', [
            'title' => 'Self-assigned', 'status' => 'backlog', 'priority' => 'low', 'assignee_id' => $operator->id,
        ])->assertRedirect();
        $this->assertSame(0, Notification::forUser($operator->id)->count());
    }

    public function test_completing_a_task_notifies_the_project_owner(): void
    {
        $owner = $this->user('admin');
        $operator = $this->user('operator');
        $project = Project::create(['name' => 'NAi rollout', 'key' => 'NAI', 'status' => 'active', 'priority' => 'high', 'owner_id' => $owner->id]);
        $task = Task::create(['project_id' => $project->id, 'title' => 'Ship notifications', 'status' => 'in_progress', 'priority' => 'high']);

        $this->actingAs($operator)->patch('/projects/'.$project->id.'/tasks/'.$task->id, ['status' => 'done'])->assertRedirect();

        $this->assertSame(1, Notification::forUser($owner->id)->where('type', 'task.completed')->count());
        $this->assertSame(0, Notification::forUser($operator->id)->count());
    }

    public function test_inbox_requires_auth_and_shows_only_own_notifications(): void
    {
        $this->get('/notifications')->assertRedirect('/login');

        $alice = $this->user('operator');
        $bob = $this->user('operator', '2');
        \App\Support\Notifier::send($alice, 'task.assigned', 'Alice task');
        \App\Support\Notifier::send($bob, 'task.assigned', 'Bob task');

        $this->actingAs($alice)->get('/notifications')
            ->assertOk()
            ->assertSee('Alice task')
            ->assertDontSee('Bob task');
    }

    public function test_mark_read_and_mark_all_read_only_touch_own_rows(): void
    {
        $alice = $this->user('operator');
        $bob = $this->user('operator', '2');
        $a1 = \App\Support\Notifier::send($alice, 'task.assigned', 'Alice one');
        $a2 = \App\Support\Notifier::send($alice, 'task.assigned', 'Alice two');
        $b1 = \App\Support\Notifier::send($bob, 'task.assigned', 'Bob one');

        $this->actingAs($alice)->post('/notifications/'.$b1->id.'/read')->assertNotFound();
        $this->assertNull($b1->fresh()->read_at);

        $this->actingAs($alice)->post('/notifications/'.$a1->id.'/read')->assertRedirect();
        $this->assertNotNull($a1->fresh()->read_at);
        $this->assertNull($a2->fresh()->read_at);

        $this->actingAs($alice)->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, Notification::forUser($alice->id)->unread()->count());
        $this->assertSame(1, Notification::forUser($bob->id)->unread()->count());
    }

    public function test_open_redirects_only_to_same_origin_paths(): void
    {
        $alice = $this->user('operator');
        $safe = \App\Support\Notifier::send($alice, 'task.assigned', 'Safe', null, '/projects');
        $evil = \App\Support\Notifier::send($alice, 'task.assigned', 'Evil', null, 'https://evil.example.test');
        $protocolRelative = \App\Support\Notifier::send($alice, 'task.assigned', 'PR', null, '//evil.example.test');

        $this->actingAs($alice)->post('/notifications/'.$safe->id.'/read', ['goto' => '1'])->assertRedirect('/projects');
        $this->actingAs($alice)->post('/notifications/'.$evil->id.'/read', ['goto' => '1'])->assertRedirect();
        $this->actingAs($alice)->post('/notifications/'.$protocolRelative->id.'/read', ['goto' => '1'])->assertRedirect();
        $this->assertNull($evil->fresh()->safeUrl());
        $this->assertNull($protocolRelative->fresh()->safeUrl());
    }

    public function test_topbar_bell_shows_unread_count(): void
    {
        $alice = $this->user('operator');
        \App\Support\Notifier::send($alice, 'task.assigned', 'Unread one');
        \App\Support\Notifier::send($alice, 'task.assigned', 'Unread two');

        $this->actingAs($alice)->get('/dashboard')
            ->assertOk()
            ->assertSee('bell-count', false)
            ->assertSee('Unread one');
    }
}
