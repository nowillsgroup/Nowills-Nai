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
        // Offline, permissive probe: this test asserts markup, not arena.ai's policy.
        Http::fake(['*' => Http::response('', 200)]);

        $this->actingAs($this->user())->get('/arena/browser')
            ->assertOk()
            ->assertSee('id="arenaFrame"', false)
            ->assertSee('src="https://arena.ai"', false)
            ->assertSee('id="sidebar"', false)
            ->assertSee('sidebar-brand', false)
            ->assertSee('content-fluid', false)
            ->assertSee('Open in tab')
            ->assertDontSee('Arena AI')
            ->assertSee('arena-browser.js', false)
            ->assertSee('data-probe="/arena/browser/probe"', false);
    }

    public function test_only_the_browser_nav_item_is_active_on_the_browser_page(): void
    {
        // Offline, permissive probe: this test asserts markup, not arena.ai's policy.
        Http::fake(['*' => Http::response('', 200)]);

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

    public function test_user_visible_branding_is_nai_not_arena_ai(): void
    {
        // Offline, permissive probe: this test asserts markup, not arena.ai's policy.
        Http::fake(['*' => Http::response('', 200)]);

        $user = $this->user();
        foreach (['/arena/browser', '/arena', '/dashboard', '/settings', '/notifications'] as $path) {
            $html = $this->actingAs($user)->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('Arena AI', $html, 'Arena AI branding leaked on '.$path);
        }
    }

    public function test_frame_is_not_credential_proxied(): void
    {
        // Offline, permissive probe: this test asserts markup, not arena.ai's policy.
        Http::fake(['*' => Http::response('', 200)]);

        $html = $this->actingAs($this->user())->get('/arena/browser')->assertOk()->getContent();
        $this->assertStringNotContainsString('type="password"', $html);
        $this->assertStringContainsString('sandbox="allow-same-origin allow-scripts allow-forms', $html);
        $this->assertStringNotContainsString('allow-storage-access-by-activation', $html);
        $this->assertStringContainsString('title="NAi browser"', $html);
    }

    public function test_csp_allows_https_frames_but_keeps_everything_else_locked(): void
    {
        // Offline, permissive probe: this test asserts markup, not arena.ai's policy.
        Http::fake(['*' => Http::response('', 200)]);

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
        // Offline, permissive probe: this test asserts markup, not arena.ai's policy.
        Http::fake(['*' => Http::response('', 200)]);

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

    /**
     * The browser layout must not live in nai.css. A visitor holding a cached
     * copy of nai.css from a previous release would get new markup with none of
     * the browser rules, collapsing the frame to the 300x150 iframe default and
     * leaving the address bar and focus button unstyled.
     */
    public function test_browser_layout_ships_in_its_own_cache_busted_stylesheet(): void
    {
        // Offline, permissive probe: this test asserts markup, not arena.ai's policy.
        Http::fake(['*' => Http::response('', 200)]);

        $content = $this->actingAs($this->user())->get('/arena/browser')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="/css/nai\.css\?v=[a-f0-9]+"#', $content);
        $this->assertMatchesRegularExpression('#href="/css/arena-browser\.css\?v=[a-f0-9]+"#', $content);
        $this->assertMatchesRegularExpression('#src="/js/arena-browser\.js\?v=[a-f0-9]+"#', $content);

        // The layout rules really are in the dedicated file, not the shared one.
        $browserCss = file_get_contents(public_path('css/arena-browser.css'));
        $sharedCss = file_get_contents(public_path('css/nai.css'));
        foreach (['.browser-chrome', '.browser-frame', '.browser-exit-focus', 'body.fluid-page', '.content-fluid'] as $rule) {
            $this->assertStringContainsString($rule, $browserCss, $rule.' must ship in arena-browser.css');
            $this->assertStringNotContainsString($rule, $sharedCss, $rule.' must not depend on cached nai.css');
        }
    }

    public function test_asset_version_changes_when_the_file_changes(): void
    {
        $path = 'css/arena-browser.css';
        $file = public_path($path);
        $original = file_get_contents($file);
        $before = \App\Support\AssetVersion::url($path);

        try {
            file_put_contents($file, $original."\n/* version probe */\n");
            touch($file, time() + 5);
            clearstatcache();
            $after = \App\Support\AssetVersion::url($path);
            $this->assertNotSame($before, $after, 'A changed asset must produce a new URL so caches are bypassed.');
        } finally {
            file_put_contents($file, $original);
            clearstatcache();
        }
    }

    /**
     * A refusal must be explained on first paint, with a way forward -- never a
     * broken-page icon inside a dead frame.
     */
    public function test_refused_site_renders_an_explanation_instead_of_a_dead_frame(): void
    {
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'SAMEORIGIN'])]);

        $response = $this->actingAs($this->user())->get('/arena/browser')->assertOk();
        $response->assertSee('id="browserBlocked"', false)
            ->assertSee('won’t run inside a frame', false)
            ->assertSee('X-Frame-Options: SAMEORIGIN', false)
            ->assertSee('id="browserBlockedOpen"', false)
            ->assertSee('Check again', false)
            ->assertSee('data-status="blocked"', false);

        // The doomed iframe must not be given a src, and the spinner must be off.
        $this->assertDoesNotMatchRegularExpression('/id="arenaFrame"[^>]*\ssrc=/', $response->getContent());
    }

    public function test_allowed_site_gets_a_frame_src_on_first_paint(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $response = $this->actingAs($this->user())->get('/arena/browser')->assertOk();
        $this->assertMatchesRegularExpression('/id="arenaFrame"[^>]*\ssrc="https:\/\/arena\.ai"/', $response->getContent());
        $response->assertSee('data-status="allowed"', false);
    }

    public function test_probe_accepts_a_destination_from_the_address_bar(): void
    {
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'DENY'])]);

        $this->actingAs($this->user())->getJson('/arena/browser/probe?url=https%3A%2F%2Fexample.com%2Fdocs')
            ->assertOk()
            ->assertJsonPath('status', 'blocked')
            ->assertJsonPath('url', 'https://example.com/docs');
    }

    /**
     * The probe is an outbound server-side request. Without a guard any signed-in
     * user could aim it at localhost or cloud metadata and read back reachability.
     */
    public function test_probe_refuses_to_scan_internal_networks(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $this->actingAs($this->user());

        foreach ([
            'http://127.0.0.1/admin',
            'http://localhost:8080/',
            'http://10.0.0.5/',
            'http://192.168.1.1/',
            'http://169.254.169.254/latest/meta-data/',
            'http://[::1]/',
            'file:///etc/passwd',
        ] as $target) {
            $this->getJson('/arena/browser/probe?url='.urlencode($target))
                ->assertOk()
                ->assertJsonPath('status', 'unknown');
        }

        Http::assertNothingSent();
    }

    public function test_probe_rejects_non_http_schemes_and_odd_ports(): void
    {
        $this->assertFalse(\App\Support\FrameProbe::isSafeTarget('ftp://example.com/'));
        $this->assertFalse(\App\Support\FrameProbe::isSafeTarget('javascript:alert(1)'));
        $this->assertFalse(\App\Support\FrameProbe::isSafeTarget('http://example.com:22/'));
        $this->assertFalse(\App\Support\FrameProbe::isSafeTarget('http://metadata.internal/'));
    }

    public function test_probe_follows_redirects_and_reports_the_final_header(): void
    {
        Http::fake([
            'https://example.com/start' => Http::response('', 302, ['Location' => 'https://example.com/final']),
            '*' => Http::response('', 200, ['X-Frame-Options' => 'DENY']),
        ]);

        $this->actingAs($this->user())->getJson('/arena/browser/probe?url='.urlencode('https://example.com/start'))
            ->assertOk()
            ->assertJsonPath('status', 'blocked')
            ->assertJsonPath('final_url', 'https://example.com/final');
    }

    /**
     * A public hostname that resolves into private space must still be refused:
     * checking only the URL shape would let DNS rebinding reach the internal
     * network on the actual connection.
     */
    public function test_probe_refuses_a_public_name_that_resolves_into_private_space(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->actingAs($this->user())->getJson('/arena/browser/probe?url='.urlencode('https://rebind.example.com/'))
            ->assertOk()
            ->assertJsonPath('status', 'unknown');

        Http::assertNothingSent();
    }

    /**
     * The probe runs during page render, so a slow origin must not be able to
     * stall the response. With the budget exhausted it stops issuing requests
     * and reports "unknown" instead of chasing the confirm-GET and redirects.
     */
    public function test_probe_stops_when_its_wall_clock_budget_is_exhausted(): void
    {
        config(['nai.arena.probe_budget' => 0.2]);
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            usleep(300000); // each request overruns the whole budget on its own

            return Http::response('', 302, ['Location' => 'https://example.com/next'.$calls]);
        });

        $start = microtime(true);
        $result = $this->actingAs($this->user())
            ->getJson('/arena/browser/probe?url='.urlencode('https://example.com/start'))
            ->assertOk()
            ->assertJsonPath('status', 'unknown');
        $elapsed = microtime(true) - $start;

        $this->assertSame(1, $calls, 'The probe must stop once its budget is spent.');
        $this->assertLessThan(2.0, $elapsed, 'Page render must not be held hostage by a slow origin.');
    }

    /**
     * An inconclusive verdict is still cached briefly: a slow origin must not
     * make every subsequent page load pay the full budget again.
     */
    public function test_inconclusive_verdict_is_cached_to_absorb_repeat_hits(): void
    {
        config(['nai.arena.probe_budget' => 0.2]);
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            usleep(300000);

            return Http::response('', 302, ['Location' => 'https://example.com/loop'.$calls]);
        });

        $user = $this->user();
        $target = '/arena/browser/probe?url='.urlencode('https://example.com/start');

        $this->actingAs($user)->getJson($target)->assertOk()->assertJsonPath('status', 'unknown');
        $this->actingAs($user)->getJson($target)->assertOk()->assertJsonPath('status', 'unknown');

        $this->assertSame(1, $calls, 'A repeat hit must be served from cache, not re-probed.');
    }
}
