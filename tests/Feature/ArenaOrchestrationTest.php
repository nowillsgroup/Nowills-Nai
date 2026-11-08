<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ArenaConnection;
use App\Models\Conversation;
use App\Models\Incident;
use App\Models\Message;
use App\Models\Project;
use App\Models\ResourceCheck;
use App\Models\Task;
use App\Models\User;
use App\Services\ArenaGateway;
use App\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArenaOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin', string $suffix = ''): User
    {
        return User::firstOrCreate(['email' => $role.$suffix.'@test.local'], ['name' => ucfirst($role), 'password' => 'password-123', 'role' => $role]);
    }

    private function application(): Application
    {
        return Application::create(['name' => 'Customer Portal', 'slug' => 'customer-portal', 'url' => 'https://portal.example.test', 'environment' => 'production', 'status' => 'degraded']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'NAi rollout', 'key' => 'NAI', 'status' => 'active', 'priority' => 'high']);
    }

    public function test_scoped_landing_lists_playbooks_and_rejects_unknown_subjects(): void
    {
        $app = $this->application();
        $this->actingAs($this->user())->get('/arena/start/applications/'.$app->id)
            ->assertOk()->assertSee('Customer Portal')->assertSee('Incident triage')->assertSee('Draft runbook');

        $this->actingAs($this->user())->get('/arena/start/applications/99999')->assertNotFound();
        $this->actingAs($this->user())->get('/arena/start/widgets/1')->assertNotFound();
    }

    public function test_playbook_creates_scoped_conversation_without_fabricating_a_reply(): void
    {
        $app = $this->application();
        $response = $this->actingAs($this->user())->post('/arena/conversations', [
            'subject_type' => 'applications', 'subject_id' => $app->id, 'playbook' => 'incident-triage',
        ])->assertRedirect();

        $conversation = Conversation::first();
        $this->assertSame('applications', $conversation->subject_type);
        $this->assertSame($app->id, $conversation->subject_id);
        $this->assertSame('incident-triage', $conversation->playbook);
        $this->assertStringContainsString('Customer Portal', $conversation->messages()->where('role', 'user')->value('content'));
        $this->assertStringContainsString('Incident triage', $conversation->title);

        // Arena is unconfigured: the failure must be recorded, never answered locally.
        $assistant = $conversation->messages()->where('role', 'assistant')->first();
        $this->assertSame('failed', $assistant->status);
        $this->assertSame('Arena AI did not return a response.', $assistant->content);
        $this->assertFalse($assistant->isActionable());
        $this->assertDatabaseHas('activities', ['action' => 'arena.scoped']);
        $this->assertStringContainsString('/arena/conversations/'.$conversation->id, $response->headers->get('Location'));
    }

    public function test_gateway_sends_scoped_subject_context_and_playbook_upstream(): void
    {
        ArenaConnection::create(['base_url' => 'https://arena.example.test', 'api_key' => 'test-key', 'status' => 'connected']);
        Http::fake(['*' => Http::response(['reply' => 'Upstream answer', 'request_id' => 'req-1'], 200)]);
        $app = $this->application();
        ResourceCheck::create(['resource_type' => 'applications', 'resource_id' => $app->id, 'resource_name' => $app->name, 'status' => 'degraded', 'http_code' => 503, 'latency_ms' => 812, 'source' => 'probe', 'checked_at' => now()]);
        Incident::create(['resource_type' => 'applications', 'resource_id' => $app->id, 'resource_name' => $app->name, 'title' => 'Portal is degraded', 'severity' => 'major', 'status' => 'open', 'trigger_status' => 'degraded', 'check_count' => 2, 'started_at' => now()->subMinutes(30), 'last_seen_at' => now()]);

        $conversation = Conversation::create(['user_id' => $this->user()->id, 'title' => 'Scoped', 'subject_type' => 'applications', 'subject_id' => $app->id, 'playbook' => 'incident-triage', 'last_message_at' => now()]);
        app(ArenaGateway::class)->chat($conversation, 'Triage this');

        Http::assertSent(function ($request) use ($app) {
            $subject = $request['context']['subject'] ?? [];
            return ($subject['name'] ?? null) === 'Customer Portal'
                && ($subject['attributes']['status'] ?? null) === 'degraded'
                && ($subject['monitoring']['recent_checks'][0]['http_code'] ?? null) === 503
                && ($subject['monitoring']['open_incidents'][0]['severity'] ?? null) === 'major'
                && ($request['context']['playbook'] ?? null) === 'incident-triage'
                && ($request['context']['platform'] ?? null) === 'NAi (Nowills AI)'
                && count($request['messages']) === 1;
        });
    }

    public function test_reply_can_be_saved_as_a_project_task_linked_to_the_message(): void
    {
        $user = $this->user();
        $project = $this->project();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Chat', 'last_message_at' => now()]);
        $message = Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => "Rotate the TLS certificate\nVerify the chain after rotation.", 'status' => 'complete']);

        $this->actingAs($user)->post('/arena/messages/'.$message->id.'/task', [
            'project_id' => $project->id, 'title' => 'Rotate the TLS certificate', 'priority' => 'high',
        ])->assertRedirect()->assertSessionHas('success');

        $task = Task::first();
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame($message->id, $task->source_message_id);
        $this->assertSame('backlog', $task->status);
        $this->assertSame('high', $task->priority);
        $this->assertStringContainsString('Verify the chain', $task->description);
        $this->assertDatabaseHas('activities', ['action' => 'task.created']);

        // A second attempt must not duplicate the work.
        $this->actingAs($user)->post('/arena/messages/'.$message->id.'/task', [
            'project_id' => $project->id, 'title' => 'Duplicate', 'priority' => 'low',
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, Task::count());
    }

    public function test_failed_replies_and_user_messages_cannot_become_tasks(): void
    {
        $user = $this->user();
        $project = $this->project();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Chat', 'last_message_at' => now()]);
        $failed = Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Arena AI did not return a response.', 'status' => 'failed']);
        $own = Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'My prompt', 'status' => 'complete']);

        foreach ([$failed, $own] as $message) {
            $this->actingAs($user)->post('/arena/messages/'.$message->id.'/task', [
                'project_id' => $project->id, 'title' => 'Nope', 'priority' => 'low',
            ])->assertStatus(422);
        }
        $this->assertSame(0, Task::count());
    }

    public function test_task_creation_respects_ownership_and_roles(): void
    {
        $owner = $this->user('operator');
        $project = $this->project();
        $conversation = Conversation::create(['user_id' => $owner->id, 'title' => 'Chat', 'last_message_at' => now()]);
        $message = Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Real upstream reply', 'status' => 'complete']);
        $payload = ['project_id' => $project->id, 'title' => 'Task', 'priority' => 'medium'];

        $this->actingAs($this->user('admin', '-other'))->post('/arena/messages/'.$message->id.'/task', $payload)->assertForbidden();
        $this->actingAs($this->user('viewer'))->post('/arena/messages/'.$message->id.'/task', $payload)->assertForbidden();
        $this->assertSame(0, Task::count());
    }

    public function test_project_conversations_include_the_task_board_in_context(): void
    {
        $project = $this->project();
        $project->tasks()->create(['title' => 'Ship inventory import', 'status' => 'in_progress', 'priority' => 'high']);
        $project->tasks()->create(['title' => 'Write docs', 'status' => 'done', 'priority' => 'low']);

        $context = app(WorkspaceContext::class)->forSubject('projects', $project->id);

        $this->assertSame('Project', $context['label']);
        $this->assertCount(2, $context['tasks']);
        $this->assertSame(50, $context['progress_percent']);
        $this->assertArrayNotHasKey('monitoring', $context);
        $this->assertNull(app(WorkspaceContext::class)->forSubject('projects', 99999));
    }

    public function test_unknown_playbook_is_rejected(): void
    {
        $app = $this->application();
        $this->actingAs($this->user())->post('/arena/conversations', [
            'subject_type' => 'applications', 'subject_id' => $app->id, 'playbook' => 'not-a-playbook',
        ])->assertStatus(422);
        $this->assertSame(0, Conversation::count());
    }

    public function test_conversation_view_shows_scope_and_saved_task_link(): void
    {
        $user = $this->user();
        $app = $this->application();
        $project = $this->project();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Scoped chat', 'subject_type' => 'applications', 'subject_id' => $app->id, 'playbook' => 'health-review', 'last_message_at' => now()]);
        $message = Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Upstream reply', 'status' => 'complete']);
        $project->tasks()->create(['title' => 'Follow-up', 'status' => 'backlog', 'priority' => 'medium', 'source_message_id' => $message->id]);

        $this->actingAs($user)->get('/arena/conversations/'.$conversation->id)
            ->assertOk()->assertSee('Customer Portal')->assertSee('Health review')->assertSee('Saved as task in NAI');
    }
}
