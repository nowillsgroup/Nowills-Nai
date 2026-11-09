<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArenaLauncherTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $role.'@test.local', 'password' => 'password-123', 'role' => $role]);
    }

    public function test_launcher_opens_the_internal_nai_workspace_in_the_same_tab(): void
    {
        foreach (['admin', 'operator', 'viewer'] as $role) {
            $user = User::create(['name' => ucfirst($role), 'email' => $role.'@test.local', 'password' => 'password-123', 'role' => $role]);
            $this->actingAs($user)->get('/dashboard')
                ->assertOk()
                ->assertSee('arena-launch', false)
                ->assertSee('href="'.route('arena.browser').'"', false)
                ->assertSee('nav-arena', false)
                ->assertDontSee('target="_blank"', false);
        }
    }

    public function test_arena_page_is_the_workspace_itself(): void
    {
        $this->actingAs($this->user())->get('/arena')
            ->assertOk()
            ->assertSee('Build with')
            ->assertSee('arenaFirstPrompt')
            ->assertSee('Start conversation')
            ->assertDontSee('Launch Arena AI');
    }

    public function test_settings_exposes_the_frame_address(): void
    {
        $this->actingAs($this->user())->get('/settings')
            ->assertOk()
            ->assertSee('Arena AI browser')
            ->assertSee('name="arena_portal_url"', false);
    }

    public function test_non_admin_cannot_update_workspace_settings(): void
    {
        $this->actingAs($this->user('operator'))->put('/settings', [
            'workspace_name' => 'Nowills Group', 'chat_path' => '/v1/chat', 'health_path' => '/v1/health',
        ])->assertForbidden();
    }
}
