<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ArenaGateway;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArenaController extends Controller
{
    public function index(ArenaGateway $arena)
    {
        $conversations = Conversation::where('user_id', auth()->id())->latest('last_message_at')->latest('id')->limit(30)->get();
        return view('arena.index', ['conversation' => null, 'conversations' => $conversations, 'arenaStatus' => $arena->status(), 'arenaConfigured' => $arena->isConfigured()]);
    }

    public function show(Conversation $conversation, ArenaGateway $arena)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);
        $conversation->load('messages');
        $conversations = Conversation::where('user_id', auth()->id())->latest('last_message_at')->latest('id')->limit(30)->get();
        return view('arena.index', compact('conversation', 'conversations') + ['arenaStatus' => $arena->status(), 'arenaConfigured' => $arena->isConfigured()]);
    }

    public function store(Request $request, ArenaGateway $arena)
    {
        $data = $request->validate(['prompt' => 'nullable|string|max:10000', 'title' => 'nullable|string|max:180']);
        $conversation = Conversation::create(['user_id' => auth()->id(), 'title' => $data['title'] ?? (filled($data['prompt'] ?? null) ? Str::limit($data['prompt'], 60) : 'New conversation'), 'last_message_at' => now()]);
        if (filled($data['prompt'] ?? null)) $this->processPrompt($conversation, $data['prompt'], $arena);
        return redirect()->route('arena.show', $conversation);
    }

    public function message(Request $request, Conversation $conversation, ArenaGateway $arena)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);
        $data = $request->validate(['content' => 'required|string|max:10000']);
        $this->processPrompt($conversation, $data['content'], $arena);
        return redirect()->route('arena.show', $conversation);
    }

    public function destroy(Conversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);
        $conversation->delete();
        return redirect()->route('arena.index')->with('success', 'Conversation deleted.');
    }

    public function test(ArenaGateway $arena)
    {
        $result = $arena->healthCheck();
        Audit::log('arena.test', 'Tested the Arena AI connection: '.$result['status']);
        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function processPrompt(Conversation $conversation, string $prompt, ArenaGateway $arena): void
    {
        Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => $prompt, 'status' => 'complete']);
        try {
            $reply = $arena->chat($conversation, $prompt);
            Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => $reply['content'], 'status' => 'complete', 'arena_request_id' => $reply['request_id'], 'tokens' => $reply['tokens']]);
            $conversation->update(['last_message_at' => now(), 'arena_session_id' => $reply['session_id'] ?? $conversation->arena_session_id, 'title' => $conversation->title === 'New conversation' ? Str::limit($prompt, 60) : $conversation->title]);
            Audit::log('arena.chat', 'Sent a prompt to Arena AI', $conversation);
        } catch (\Throwable $e) {
            Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Arena AI did not return a response.', 'status' => 'failed', 'error' => $e->getMessage()]);
            $conversation->update(['last_message_at' => now()]);
            Audit::log('arena.error', 'Arena AI request failed: '.$e->getMessage(), $conversation);
        }
    }
}
