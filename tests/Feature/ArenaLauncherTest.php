<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ArenaPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArenaLauncherTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $role.'@test.local', 'password' => 'password-123', 'role' => $role]);
    }

    public function test_portal_defaults_to_arena_ai(): void
    {
        $this->assertSame('https://arena.ai', ArenaPortal::url());
    }

    public function test_launcher_appears_in_topbar_and_sidebar_for_all_roles(): void
    {
        foreach (['admin', 'operator', 'viewer'] as $role) {
            $user = User::create(['name' => ucfirst($role), 'email' => $role.'@test.local', 'password' => 'password-123', 'role' => $role]);
            $this->actingAs($user)->get('/dashboard')
                ->assertOk()
                ->assertSee('arena-launch', false)
                ->assertSee('href="https://arena.ai"', false)
                ->assertSee('target="_blank"', false)
                ->assertSee('rel="noopener noreferrer"', false)
                ->assertSee('nav-external', false);
        }
    }

    public function test_arena_page_shows_launch_card(): void
    {
        $this->actingAs($this->user())->get('/arena')
            ->assertOk()
            ->assertSee('Launch Arena AI')
            ->assertSee('arena-launch-card', false);
    }

    public function test_admin_can_configure_the_portal_url(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->put('/settings', [
            'workspace_name' => 'Nowills Group',
            'chat_path' => '/v1/chat',
            'health_path' => '/v1/health',
            'arena_portal_url' => 'https://arena.ai/chat',
        ])->assertRedirect();

        $this->assertSame('https://arena.ai/chat', ArenaPortal::url());
        $this->actingAs($admin)->get('/dashboard')->assertSee('href="https://arena.ai/chat"', false);
    }

    public function test_non_admin_cannot_change_the_portal_url(): void
    {
        $this->actingAs($this->user('operator'))->put('/settings', [
            'workspace_name' => 'Nowills Group', 'chat_path' => '/v1/chat', 'health_path' => '/v1/health', 'arena_portal_url' => 'https://evil.example.test',
        ])->assertForbidden();
        $this->assertSame('https://arena.ai', ArenaPortal::url());
    }

    public function test_unsafe_urls_are_rejected_and_default_is_used(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,hi', '//evil.example.test', '/relative', 'ftp://arena.ai', ''] as $bad) {
            $this->assertNull(ArenaPortal::sanitize($bad), 'should reject: '.$bad);
        }
        $this->assertSame('https://arena.ai', ArenaPortal::sanitize('https://arena.ai'));
        $this->assertSame('http://localhost:3000', ArenaPortal::sanitize('http://localhost:3000'));
    }

    public function test_stored_unsafe_value_falls_back_to_default(): void
    {
        Setting::create(['key' => 'arena_portal_url', 'value' => 'javascript:alert(1)']);
        $this->assertSame('https://arena.ai', ArenaPortal::url());
    }

    public function test_settings_validation_rejects_non_url_portal(): void
    {
        $this->actingAs($this->user())->put('/settings', [
            'workspace_name' => 'Nowills Group', 'chat_path' => '/v1/chat', 'health_path' => '/v1/health', 'arena_portal_url' => 'not-a-url',
        ])->assertSessionHasErrors('arena_portal_url');
    }
}
