<?php

namespace App\Services;

use App\Models\ArenaConnection;
use App\Models\Conversation;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Boundary to the upstream NAi development engine.
 *
 * NAi deliberately contains no model, prompt-completion engine, or local
 * inference fallback. Configure this gateway with the approved NAi
 * endpoint/bridge for your NAi deployment. The default request/response
 * contract is documented in docs/ARENA-INTEGRATION.md.
 */
class ArenaGateway
{
    public function __construct(private WorkspaceContext $context) {}

    public function connection(): ArenaConnection
    {
        return ArenaConnection::query()->first() ?? new ArenaConnection([
            'base_url' => config('nai.arena.base_url'),
            'api_key' => config('nai.arena.api_key'),
            'workspace_ref' => config('nai.arena.workspace_ref'),
            'chat_path' => config('nai.arena.chat_path'),
            'health_path' => config('nai.arena.health_path'),
            'status' => 'disconnected',
        ]);
    }

    public function settings(): array
    {
        $c = $this->connection();
        return [
            'base_url' => $c->base_url ?: config('nai.arena.base_url'),
            'api_key' => $c->api_key ?: config('nai.arena.api_key'),
            'workspace_ref' => $c->workspace_ref ?: config('nai.arena.workspace_ref'),
            'chat_path' => $c->chat_path ?: config('nai.arena.chat_path'),
            'health_path' => $c->health_path ?: config('nai.arena.health_path'),
        ];
    }

    public function isConfigured(): bool
    {
        return filled($this->settings()['base_url']);
    }

    public function status(): string
    {
        if (! $this->isConfigured()) return 'disconnected';
        return $this->connection()->status ?: 'unknown';
    }

    public function healthCheck(): array
    {
        $s = $this->settings();
        $c = ArenaConnection::query()->firstOrCreate([], ['chat_path' => '/v1/chat', 'health_path' => '/v1/health']);
        if (! filled($s['base_url'])) {
            $c->update(['status' => 'disconnected', 'last_error' => null, 'last_checked_at' => now()]);
            return ['ok' => false, 'status' => 'disconnected', 'message' => 'Add an NAi endpoint to enable the engine connection.'];
        }
        try {
            $response = $this->request($s)->timeout((int) config('nai.arena.timeout', 45))->get($this->url($s['base_url'], $s['health_path']));
            if ($response->successful()) {
                $c->update(['status' => 'connected', 'last_error' => null, 'last_checked_at' => now()]);
                return ['ok' => true, 'status' => 'connected', 'message' => 'NAi responded successfully.'];
            }
            $msg = 'NAi returned HTTP '.$response->status().'.';
            $c->update(['status' => 'error', 'last_error' => $msg, 'last_checked_at' => now()]);
            return ['ok' => false, 'status' => 'error', 'message' => $msg];
        } catch (\Throwable $e) {
            $msg = 'Unable to reach NAi. Check the endpoint, network, and credentials.';
            $c->update(['status' => 'error', 'last_error' => $msg, 'last_checked_at' => now()]);
            return ['ok' => false, 'status' => 'error', 'message' => $msg];
        }
    }

    public function chat(Conversation $conversation, string $prompt): array
    {
        $s = $this->settings();
        if (! filled($s['base_url'])) {
            throw new RuntimeException('NAi is not connected. Configure the NAi endpoint in Settings before sending prompts.');
        }

        $history = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])->where('status', 'complete')
            ->latest('id')->limit(20)->get()->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->values()->all();
        $last = $history[count($history) - 1] ?? null;
        if (! $last || $last['role'] !== 'user' || $last['content'] !== $prompt) {
            $history[] = ['role' => 'user', 'content' => $prompt];
        }

        $context = $this->context->snapshot();
        if ($conversation->subject_type && $conversation->subject_id) {
            $subject = $this->context->forSubject($conversation->subject_type, (int) $conversation->subject_id);
            if ($subject) $context['subject'] = $subject;
        }
        if ($conversation->playbook) $context['playbook'] = $conversation->playbook;

        $payload = [
            'workspace' => $s['workspace_ref'] ?: config('nai.workspace'),
            'conversation_id' => 'nai-'.$conversation->id,
            'session_id' => $conversation->arena_session_id,
            'messages' => $history,
            'context' => $context,
            'stream' => false,
        ];

        try {
            $response = $this->request($s)
                ->timeout((int) config('nai.arena.timeout', 45))
                ->post($this->url($s['base_url'], $s['chat_path']), $payload);
        } catch (RequestException $e) {
            throw new RuntimeException('NAi request failed with HTTP '.$e->response->status().'.');
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to reach NAi. The prompt was not answered by a local model.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('NAi returned HTTP '.$response->status().'.');
        }

        $json = $response->json();
        $content = $this->normalizeReply(is_array($json) ? $json : []);
        if ($content === null || trim($content) === '') {
            throw new RuntimeException('NAi returned an empty or unsupported response. Check the bridge response contract.');
        }

        return [
            'content' => $content,
            'request_id' => $json['request_id'] ?? $json['id'] ?? null,
            'session_id' => $json['session_id'] ?? null,
            'tokens' => $json['usage']['total_tokens'] ?? null,
        ];
    }

    private function request(array $s): \Illuminate\Http\Client\PendingRequest
    {
        $req = Http::acceptJson()->asJson()->connectTimeout(8);
        if (filled($s['api_key'])) $req = $req->withToken($s['api_key']);
        if (filled($s['workspace_ref'])) $req = $req->withHeaders(['X-NAi-Workspace' => $s['workspace_ref']]);
        return $req;
    }

    private function url(string $base, string $path): string
    {
        return rtrim($base, '/').'/'.ltrim($path ?: '/', '/');
    }

    private function normalizeReply(array $json): ?string
    {
        foreach ([
            data_get($json, 'data.reply'),
            data_get($json, 'reply'),
            data_get($json, 'message.content'),
            data_get($json, 'choices.0.message.content'),
            data_get($json, 'output_text'),
            data_get($json, 'content'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') return $candidate;
        }
        return null;
    }
}
