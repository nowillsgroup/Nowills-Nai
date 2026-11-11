<?php

namespace Tests\Feature;

use App\Models\ArenaConnection;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ArenaGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiCodingAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password-123', 'role' => 'admin']);
    }

    private function settings(array $overrides = []): array
    {
        return array_merge(['workspace_name' => 'Nowills Group', 'provider' => 'openai', 'model' => 'gpt-5.4', 'chat_path' => '/v1/chat', 'health_path' => '/v1/health'], $overrides);
    }

    public function test_admin_can_configure_openai_with_encrypted_key_and_non_admin_cannot(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/settings', $this->settings(['api_key' => 'sk-test-secret']))->assertRedirect();
        $c = ArenaConnection::firstOrFail();
        $this->assertSame('openai', $c->provider);
        $this->assertSame('gpt-5.4', $c->model);
        $this->assertSame('sk-test-secret', $c->api_key);
        $this->assertStringNotContainsString('sk-test-secret', (string) \DB::table('arena_connections')->value('api_key'));
        $this->get('/settings')->assertOk()->assertSee('OpenAI coding assistant')->assertDontSee('sk-test-secret');
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@example.test', 'password' => 'password-123', 'role' => 'viewer']);
        $this->actingAs($viewer)->put('/settings', $this->settings(['api_key' => 'replacement']))->assertForbidden();
    }

    public function test_switching_provider_clears_old_key_unless_a_new_key_is_supplied(): void
    {
        $this->actingAs($this->admin());
        ArenaConnection::create(['provider' => 'bridge', 'api_key' => 'bridge-secret', 'base_url' => 'https://example.test']);
        $this->put('/settings', $this->settings())->assertRedirect();
        $this->assertNull(ArenaConnection::firstOrFail()->api_key);
        $this->assertFalse(app(ArenaGateway::class)->isConfigured());
        $this->put('/settings', $this->settings(['api_key' => 'openai-key']))->assertRedirect();
        $this->put('/settings', $this->settings())->assertRedirect();
        $this->assertSame('openai-key', ArenaConnection::firstOrFail()->api_key);
        $this->put('/settings', $this->settings(['provider' => 'bridge', 'api_key' => 'new-bridge-key']))->assertRedirect();
        $this->assertSame('new-bridge-key', ArenaConnection::firstOrFail()->api_key);
    }

    public function test_openai_chat_sends_actual_conversation_to_fixed_endpoint_and_displays_response(): void
    {
        $admin = $this->admin();
        ArenaConnection::create(['provider' => 'openai', 'model' => 'gpt-5.4', 'api_key' => 'openai-secret', 'base_url' => 'https://untrusted.example.test', 'status' => 'disconnected']);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['id' => 'chat-123', 'choices' => [['message' => ['role' => 'assistant', 'content' => "Here's the code:\n```php\necho 'hello';\n```"]]], 'usage' => ['total_tokens' => 42]])]);
        $this->actingAs($admin)->post('/arena/conversations', ['prompt' => 'Write PHP hello world'])->assertRedirect();
        $conversation = Conversation::firstOrFail();
        $this->assertSame('complete', $conversation->messages()->where('role', 'assistant')->firstOrFail()->status);
        $this->get('/arena/conversations/'.$conversation->id)->assertOk()->assertSee('Here&#039;s the code:', false)->assertSee('echo &#039;hello&#039;', false);
        Http::assertSent(function ($request) {
            $messages = $request['messages'];
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer openai-secret')
                && $request['model'] === 'gpt-5.4'
                && end($messages)['content'] === 'Write PHP hello world'
                && ! str_contains(json_encode($request->data()), 'openai-secret');
        });
    }

    public function test_openai_model_check_and_errors_are_honest_and_do_not_expose_key(): void
    {
        $this->actingAs($this->admin());
        ArenaConnection::create(['provider' => 'openai', 'model' => 'gpt-5.4', 'api_key' => 'openai-secret']);
        Http::fake(['api.openai.com/v1/models/*' => Http::response(['id' => 'gpt-5.4'])]);
        $result = app(ArenaGateway::class)->healthCheck();
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('model lookup', $result['message']);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.openai.com/v1/models/gpt-5.4' && $r->hasHeader('Authorization', 'Bearer openai-secret'));
    }

    public function test_provider_error_is_saved_as_failed_not_fabricated_reply(): void
    {
        $admin = $this->admin();
        ArenaConnection::create(['provider' => 'openai', 'api_key' => 'secret']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'secret']], 401)]);
        $this->actingAs($admin)->post('/arena/conversations', ['prompt' => 'Build an app'])->assertRedirect();
        $message = Conversation::firstOrFail()->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('401', $message->error);
        $this->assertStringNotContainsString('secret', $message->error);
    }

    public function test_missing_key_and_invalid_model_do_not_contact_provider(): void
    {
        $this->actingAs($this->admin());
        $this->put('/settings', $this->settings(['model' => '../bad', 'api_key' => 'secret-not-for-session']))
            ->assertSessionHasErrors('model')->assertSessionMissing('_old_input.api_key');
        ArenaConnection::create(['provider' => 'openai']);
        $this->assertFalse(app(ArenaGateway::class)->isConfigured());
        $this->assertFalse(app(ArenaGateway::class)->healthCheck()['ok']);
        Http::assertNothingSent();
    }
    public function test_new_install_seeder_prefers_openai_but_preserves_existing_bridge(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $connection = ArenaConnection::firstOrFail();
        $this->assertSame('openai', $connection->provider);
        $this->assertSame('gpt-5.4', $connection->model);
        $connection->update(['provider' => 'bridge', 'base_url' => 'https://old.example.test', 'api_key' => 'existing-key']);
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->assertSame('bridge', $connection->fresh()->provider);
        $this->assertSame('existing-key', $connection->fresh()->api_key);
    }

    public function test_follow_up_sends_conversation_history(): void
    {
        $admin = $this->admin();
        ArenaConnection::create(['provider' => 'openai', 'api_key' => 'openai-key']);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'First answer']]]])]);
        $this->actingAs($admin)->post('/arena/conversations', ['prompt' => 'First question']);
        $conversation = Conversation::firstOrFail();
        $this->post('/arena/conversations/'.$conversation->id.'/messages', ['content' => 'Follow-up'])->assertRedirect();
        Http::assertSent(function ($request) {
            $messages = $request['messages'];
            $tail = array_slice($messages, -3);
            return array_column($tail, 'content') === ['First question', 'First answer', 'Follow-up'];
        });
    }
}
