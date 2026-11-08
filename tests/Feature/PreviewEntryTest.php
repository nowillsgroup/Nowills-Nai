<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewEntryTest extends TestCase
{
    use RefreshDatabase;

    private function demoAdmin(): User
    {
        return User::create([
            'name' => 'Nowills Admin',
            'email' => 'demo@nowills.group',
            'password' => 'password-123',
            'role' => 'admin',
        ]);
    }

    public function test_local_preview_auto_demo_opens_the_dashboard_directly(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['nai.demo_mode' => true, 'nai.preview_auto_demo' => true, 'nai.preview_landing' => 'dashboard']);
        $demo = $this->demoAdmin();

        $this->get('/')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Dashboard');

        $this->assertAuthenticatedAs($demo);
        $this->assertDatabaseHas('activities', [
            'action' => 'auth.demo',
            'user_id' => $demo->id,
        ]);
    }

    public function test_local_preview_can_land_on_arena_ai_and_keep_dashboard_available(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['nai.demo_mode' => true, 'nai.preview_auto_demo' => true, 'nai.preview_landing' => 'arena']);
        $demo = $this->demoAdmin();

        $this->get('/')->assertRedirect('/arena');
        $this->assertAuthenticatedAs($demo);

        $this->get('/arena')
            ->assertOk()
            ->assertSee('NAi')
            ->assertSee('Build with');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Dashboard');

        $this->assertDatabaseHas('activities', [
            'action' => 'auth.demo',
            'user_id' => $demo->id,
        ]);
    }

    public function test_local_preview_can_open_arena_ai_directly_without_a_session_cookie(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['nai.demo_mode' => true, 'nai.preview_auto_demo' => true, 'nai.preview_landing' => 'arena']);
        $demo = $this->demoAdmin();

        $this->get('/arena')
            ->assertOk()
            ->assertSee('NAi')
            ->assertSee('Build with');

        $this->assertAuthenticatedAs($demo);
    }

    public function test_preview_auto_demo_requires_explicit_opt_in(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['nai.demo_mode' => true, 'nai.preview_auto_demo' => false]);
        $this->demoAdmin();

        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_preview_auto_demo_never_runs_outside_local(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['nai.demo_mode' => true, 'nai.preview_auto_demo' => true]);
        $this->demoAdmin();

        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_preview_auto_demo_requires_demo_mode(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['nai.demo_mode' => false, 'nai.preview_auto_demo' => true]);
        $this->demoAdmin();

        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }
}
