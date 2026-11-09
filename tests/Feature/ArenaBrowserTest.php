<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ArenaPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArenaBrowserTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $role.'@test.local', 'password' => 'password-123', 'role' => $role]);
    }

    public function test_browser_requires_authentication(): void
    {
        $this->get('/arena/browser')->assertRedirect('/login');
    }

    public function test_browser_frames_arena_ai_inside_the_nai_shell(): void
    {
        $this->actingAs($this->user())->get('/arena/browser')
            ->assertOk()
            ->assertSee('id="arenaFrame"', false)
            ->assertSee('src="https://arena.ai"', false)
            ->assertSee('id="sidebar"', false)
            ->assertSee('sidebar-brand', false)
            ->assertSee('content-fluid', false)
            ->assertSee('Open in tab')
            ->assertSee('arena-browser.js', false)
            ->assertSee('data-probe="/arena/browser/probe"', false);
    }

    public function test_only_the_browser_nav_item_is_active_on_the_browser_page(): void
    {
        $html = $this->actingAs($this->user())->get('/arena/browser')->assertOk()->getContent();
        $this->assertStringContainsString('class="nav-item nav-arena active"', $html);
        $this->assertStringNotContainsString('class="nav-item active"><svg', substr($html, 0, strpos($html, 'nav-arena')));
    }

    public function test_engine_nav_item_is_active_on_the_engine_page(): void
    {
        $this->actingAs($this->user())->get('/arena')
            ->assertOk()
            ->assertSee('class="nav-item active"', false)
            ->assertSee('class="nav-item nav-arena "', false);
    }

    public function test_frame_is_not_credential_proxied(): void
    {
        $html = $this->actingAs($this->user())->get('/arena/browser')->assertOk()->getContent();
        $this->assertStringNotContainsString('type="password"', $html);
        $this->assertStringContainsString('sandbox="allow-same-origin allow-scripts allow-forms', $html);
        $this->assertStringNotContainsString('allow-storage-access-by-activation', $html);
        $this->assertStringContainsString('title="Arena AI"', $html);
    }

    public function test_csp_allows_https_frames_but_keeps_everything_else_locked(): void
    {
        $csp = $this->actingAs($this->user())->get('/arena/browser')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-src 'self' https://arena.ai https:", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringNotContainsString('frame-ancestors', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function test_admin_can_point_the_frame_at_another_arena_address(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->put('/settings', [
            'workspace_name' => 'Nowills Group', 'chat_path' => '/v1/chat', 'health_path' => '/v1/health',
            'arena_portal_url' => 'https://arena.ai/history',
        ])->assertRedirect();

        $this->assertSame('https://arena.ai/history', ArenaPortal::url());
        $this->actingAs($admin)->get('/arena/browser')->assertSee('src="https://arena.ai/history"', false);
    }

    public function test_unsafe_frame_addresses_fall_back_to_the_default(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,hi', '//evil.example.test', '/relative', 'ftp://arena.ai', ''] as $bad) {
            $this->assertNull(ArenaPortal::sanitize($bad), 'should reject: '.$bad);
        }
        Setting::create(['key' => 'arena_portal_url', 'value' => 'javascript:alert(1)']);
        $this->assertSame('https://arena.ai', ArenaPortal::url());
        $this->assertSame('https://arena.ai', ArenaPortal::origin());
    }

    public function test_non_admin_cannot_change_the_frame_address(): void
    {
        $this->actingAs($this->user('operator'))->put('/settings', [
            'workspace_name' => 'Nowills Group', 'chat_path' => '/v1/chat', 'health_path' => '/v1/health',
            'arena_portal_url' => 'https://evil.example.test',
        ])->assertForbidden();
        $this->assertSame('https://arena.ai', ArenaPortal::url());
    }

    public function test_probe_reports_a_frame_refusal(): void
    {
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'DENY'])]);
        $this->actingAs($this->user())->getJson('/arena/browser/probe')
            ->assertOk()
            ->assertJsonPath('status', 'blocked')
            ->assertJsonPath('url', 'https://arena.ai');
    }

    public function test_probe_reports_frame_ancestors_none_as_blocked(): void
    {
        Http::fake(['*' => Http::response('', 200, ['Content-Security-Policy' => "default-src 'self'; frame-ancestors 'none'"])]);
        $this->actingAs($this->user())->getJson('/arena/browser/probe')->assertOk()->assertJsonPath('status', 'blocked');
    }

    public function test_probe_allows_a_site_without_frame_restrictions(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $this->actingAs($this->user())->getJson('/arena/browser/probe')->assertOk()->assertJsonPath('status', 'allowed');
    }

    public function test_probe_is_unknown_when_the_network_fails(): void
    {
        Http::fake(fn () => throw new \RuntimeException('offline'));
        $this->actingAs($this->user())->getJson('/arena/browser/probe')->assertOk()->assertJsonPath('status', 'unknown');
    }

    public function test_health_check_still_works_without_a_settings_table(): void
    {
        \Schema::drop('settings');
        $this->get('/up')->assertOk();
        $this->assertSame('https://arena.ai', ArenaPortal::url());
    }
}
