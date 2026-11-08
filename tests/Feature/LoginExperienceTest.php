<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $password = 'correct-horse- battery'): User
    {
        return User::create(['name' => 'Ama Mensah', 'email' => 'ama@nowills.group', 'password' => $password, 'role' => 'operator']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('nai-login:ama@nowills.group|127.0.0.1');
    }

    public function test_sign_in_page_renders_the_brand_experience(): void
    {
        $response = $this->get('/login')->assertOk();
        $response->assertSee('Welcome to NAi')
            ->assertSee('Sign in to workspace')
            ->assertSee('NAi')
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('js/login.js')
            ->assertSee('loginMesh')
            ->assertDontSee('login-architecture')
            ->assertDontSee('NAi runs no model of its own')
            ->assertDontSee('development work through NAi')
            ->assertSee('AI development is powered by <strong>Nowills Group</strong>.', false)
            ->assertDontSee('AI development is powered by <strong>NAi</strong>.', false);
    }

    public function test_sign_in_assets_are_same_origin_relative_for_embedded_previews(): void
    {
        // The Arena preview panel terminates TLS at a proxy; PHP may only see
        // an internal HTTP host. Assets must not inherit that internal origin.
        $response = $this->get('/login')->assertOk();
        $response->assertSee('href="/css/nai.css"', false)
            ->assertSee('src="/js/login.js"', false)
            ->assertSee('src="/images/nai-logo.png"', false)
            ->assertSee('href="/images/favicon.png"', false);

        $content = $response->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/(?:href|src)="https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?\/(?:css|js|images|fonts)\//',
            $content
        );
    }

    public function test_sign_in_page_does_not_leak_workspace_records_or_use_inline_scripts(): void
    {
        Application::create(['name' => 'Confidential Payroll Engine', 'slug' => 'confidential-payroll', 'environment' => 'production', 'status' => 'healthy']);
        $this->member();

        $content = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('Confidential Payroll Engine', $content);
        $this->assertStringNotContainsString('ama@nowills.group', $content);
        // CSP is script-src 'self' — the page must not rely on inline script.
        $this->assertStringNotContainsString('<script>', $content);
        $this->assertTrue($this->get('/login')->headers->has('Content-Security-Policy'));
    }

    public function test_demo_entry_is_only_offered_in_demo_mode(): void
    {
        config(['nai.demo_mode' => true]);
        $this->get('/login')->assertOk()->assertSee('Open the demo workspace');

        config(['nai.demo_mode' => false]);
        $this->get('/login')->assertOk()->assertDontSee('Open the demo workspace');
    }

    public function test_failed_sign_in_is_generic_and_audited_without_enumerating_accounts(): void
    {
        $this->member();

        $wrongPassword = $this->from('/login')->post('/login', ['email' => 'ama@nowills.group', 'password' => 'nope']);
        $wrongPassword->assertRedirect('/login')->assertSessionHasErrors('email');
        $unknownAccount = $this->from('/login')->post('/login', ['email' => 'ghost@nowills.group', 'password' => 'nope']);
        $unknownAccount->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertSame(session('errors')->first('email'), 'The provided credentials do not match our records.');
        $this->assertGuest();
        $this->assertDatabaseHas('activities', ['action' => 'auth.failed']);
    }

    public function test_password_is_never_reflected_back_into_the_page(): void
    {
        $this->member();
        $this->from('/login')->post('/login', ['email' => 'ama@nowills.group', 'password' => 'super-secret-value']);

        $this->get('/login')->assertOk()->assertDontSee('super-secret-value');
    }

    public function test_repeated_failures_produce_an_actionable_cooldown_message(): void
    {
        $this->member();
        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', ['email' => 'ama@nowills.group', 'password' => 'wrong-'.$i]);
        }

        $this->from('/login')->post('/login', ['email' => 'ama@nowills.group', 'password' => 'wrong-again'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_successful_sign_in_redirects_to_the_dashboard_and_clears_the_limiter(): void
    {
        $this->member('a-valid-password');
        RateLimiter::hit('nai-login:ama@nowills.group|127.0.0.1', 60);

        $this->post('/login', ['email' => 'ama@nowills.group', 'password' => 'a-valid-password', 'remember' => '1'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertSame(0, RateLimiter::attempts('nai-login:ama@nowills.group|127.0.0.1'));
        $this->assertDatabaseHas('activities', ['action' => 'auth.login']);
    }

    public function test_demo_route_is_unavailable_when_demo_mode_is_off(): void
    {
        config(['nai.demo_mode' => false]);
        $this->member();
        $this->post('/demo')->assertNotFound();
        $this->assertGuest();
    }
}
