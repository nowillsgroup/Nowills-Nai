<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ArenaConnection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ArenaGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password-123', 'role' => 'admin']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Welcome to NAi');
    }

    public function test_dashboard_renders_for_authenticated_users(): void
    {
        $this->actingAs($this->admin())->get('/')->assertOk()->assertSee('Welcome back');
    }

    public function test_admin_can_register_an_application(): void
    {
        $this->actingAs($this->admin())->post('/inventory/applications', [
            'name' => 'Test Portal', 'environment' => 'production', 'status' => 'healthy', 'tech_stack' => 'Laravel, MySQL', 'health_score' => 97,
        ])->assertRedirect('/inventory/applications');
        $this->assertDatabaseHas('applications', ['name' => 'Test Portal', 'slug' => 'test-portal', 'health_score' => 97]);
    }

    public function test_viewer_cannot_modify_inventory(): void
    {
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@test.local', 'password' => 'password-123', 'role' => 'viewer']);
        $this->actingAs($viewer)->post('/inventory/applications', ['name' => 'Nope', 'environment' => 'production', 'status' => 'healthy'])->assertForbidden();
    }

    public function test_arena_does_not_fabricate_responses_when_disconnected(): void
    {
        config(['nai.arena.base_url' => '']);
        ArenaConnection::create(['status' => 'disconnected']);
        $this->actingAs($this->admin())->post('/arena/conversations', ['prompt' => 'Plan a deployment'])->assertRedirect();
        $conversation = Conversation::firstOrFail();
        $this->assertSame(2, $conversation->messages()->count());
        $failed = $conversation->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('did not return a response', $failed->content);
    }

    public function test_arena_gateway_forwards_to_upstream_engine(): void
    {
        config(['nai.arena.base_url' => 'https://arena.example.test', 'nai.arena.api_key' => 'secret', 'nai.arena.chat_path' => '/v1/chat']);
        Http::fake(['arena.example.test/*' => Http::response(['reply' => 'Upstream Arena response', 'request_id' => 'req-123', 'usage' => ['total_tokens' => 42]])]);
        $user = $this->admin();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Test']);
        Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Review the estate', 'status' => 'complete']);
        $reply = app(ArenaGateway::class)->chat($conversation, 'Review the estate');
        $this->assertSame('Upstream Arena response', $reply['content']);
        $this->assertSame('req-123', $reply['request_id']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'arena.example.test/v1/chat')
                && $request['context']['platform'] === 'NAi (Nowills AI)'
                && $request['messages'][0]['content'] === 'Review the estate'
                && count($request['messages']) === 1;
        });
    }

    public function test_settings_store_arena_key_encrypted(): void
    {
        $this->actingAs($this->admin())->put('/settings', [
            'workspace_name' => 'Nowills Group', 'base_url' => 'https://arena.example.test', 'api_key' => 'plain-secret', 'chat_path' => '/v1/chat', 'health_path' => '/v1/health', 'workspace_ref' => 'nowills',
        ])->assertRedirect();
        $raw = \DB::table('arena_connections')->value('api_key');
        $this->assertNotSame('plain-secret', $raw);
        $this->assertSame('plain-secret', ArenaConnection::first()->api_key);
    }


    public function test_security_headers_are_present(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_inventory_csv_export_requires_auth_and_streams_csv(): void
    {
        $this->get('/inventory/applications/export')->assertRedirect('/login');
        Application::create(['name' => 'Export App', 'slug' => 'export-app', 'environment' => 'production', 'status' => 'healthy']);
        $response = $this->actingAs($this->admin())->get('/inventory/applications/export');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Export App', $response->streamedContent());
    }

    public function test_workspace_export_excludes_secrets(): void
    {
        ArenaConnection::create(['base_url' => 'https://arena.example.test', 'api_key' => 'super-secret', 'status' => 'disconnected']);
        $response = $this->actingAs($this->admin())->get('/settings/export')->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('https://arena.example.test', $content);
        $this->assertStringNotContainsString('super-secret', $content);
    }

    public function test_monitor_dry_run_does_not_mutate_status(): void
    {
        config(['nai.monitoring.timeout' => 1]);
        Http::fake(['monitor.example.test/*' => Http::response('ok', 200)]);
        $app = Application::create(['name' => 'Monitor App', 'slug' => 'monitor-app', 'url' => 'https://monitor.example.test', 'environment' => 'production', 'status' => 'unknown']);
        $result = app(\App\Services\ResourceMonitor::class)->checkApplication($app, true);
        $this->assertSame(1, $result['checked']);
        $this->assertSame('unknown', $app->fresh()->status);
    }

    public function test_admin_can_edit_team_member_and_reset_password(): void
    {
        $admin = $this->admin();
        $member = User::create(['name' => 'Member', 'email' => 'member@test.local', 'password' => 'old-password-123', 'role' => 'viewer']);
        $this->actingAs($admin)->get('/users/'.$member->id.'/edit')->assertOk();
        $this->actingAs($admin)->put('/users/'.$member->id, [
            'name' => 'Member Updated', 'email' => 'member@test.local', 'role' => 'operator', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertRedirect();
        $this->assertSame('operator', $member->fresh()->role);
        $this->assertTrue(\Hash::check('new-password-123', $member->fresh()->password));
    }

    public function test_global_search_returns_inventory_matches(): void
    {
        Application::create(['name' => 'Searchable Portal', 'slug' => 'searchable-portal', 'environment' => 'production', 'status' => 'healthy']);
        $res = $this->actingAs($this->admin())->getJson('/search?q=Searchable')->assertOk();
        $this->assertStringContainsString('Searchable Portal', $res->getContent());
    }
}
