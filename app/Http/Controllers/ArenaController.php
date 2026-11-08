<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Services\ArenaGateway;
use App\Support\Audit;
use App\Support\Playbooks;
use App\Support\Subjects;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArenaController extends Controller
{
    public function index(ArenaGateway $arena)
    {
        return view('arena.index', $this->viewData($arena));
    }

    /** Landing state for a conversation scoped to one inventory record or project. */
    public function start(string $type, int $id, ArenaGateway $arena)
    {
        abort_unless(Subjects::isValid($type), 404);
        $subject = Subjects::find($type, $id);
        abort_unless($subject !== null, 404);

        return view('arena.index', array_merge($this->viewData($arena), [
            'subject' => $subject,
            'subjectType' => $type,
            'subjectId' => $id,
            'playbooks' => Playbooks::for($type),
        ]));
    }

    public function show(Conversation $conversation, ArenaGateway $arena)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);
        $conversation->load('messages.task.project');

        return view('arena.index', array_merge($this->viewData($arena), [
            'conversation' => $conversation,
            'subject' => $conversation->subject(),
            'subjectType' => $conversation->subject_type,
            'subjectId' => $conversation->subject_id,
            'playbooks' => $conversation->subject_type ? Playbooks::for($conversation->subject_type) : [],
        ]));
    }

    public function store(Request $request, ArenaGateway $arena)
    {
        $data = $request->validate([
            'prompt' => 'nullable|string|max:10000',
            'title' => 'nullable|string|max:180',
            'subject_type' => 'nullable|string|max:30',
            'subject_id' => 'nullable|integer',
            'playbook' => 'nullable|string|max:40',
        ]);

        $subject = null;
        $subjectType = $data['subject_type'] ?? null;
        $subjectId = (int) ($data['subject_id'] ?? 0);
        if ($subjectType) {
            abort_unless(Subjects::isValid($subjectType), 404);
            $subject = Subjects::find($subjectType, $subjectId);
            abort_unless($subject !== null, 404);
        }

        $playbookKey = $data['playbook'] ?? null;
        $prompt = $data['prompt'] ?? null;
        if ($playbookKey) {
            abort_unless($subject !== null, 422, 'A playbook must be scoped to a resource.');
            $prompt = Playbooks::render($subjectType, $playbookKey, $subject->name);
            abort_unless($prompt !== null, 422, 'Unknown playbook for this resource type.');
        }

        $title = $data['title'] ?? null;
        if (! $title) {
            $title = match (true) {
                $playbookKey !== null && $subject !== null => Str::limit(Playbooks::get($subjectType, $playbookKey)['label'].' · '.$subject->name, 170),
                $subject !== null => Str::limit($subject->name.' · '.($prompt ? Str::limit($prompt, 80) : 'NAi'), 170),
                filled($prompt) => Str::limit($prompt, 60),
                default => 'New conversation',
            };
        }

        $conversation = Conversation::create([
            'user_id' => auth()->id(), 'title' => $title, 'last_message_at' => now(),
            'subject_type' => $subject ? $subjectType : null,
            'subject_id' => $subject ? $subject->id : null,
            'playbook' => $playbookKey,
        ]);

        if ($subject) Audit::log('arena.scoped', 'Started an NAi conversation scoped to '.$subjectType.' “'.$subject->name.'”', $conversation);
        if (filled($prompt)) $this->processPrompt($conversation, $prompt, $arena);

        return redirect()->route('arena.show', $conversation);
    }

    public function message(Request $request, Conversation $conversation, ArenaGateway $arena)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);
        $data = $request->validate(['content' => 'required|string|max:10000']);
        $this->processPrompt($conversation, $data['content'], $arena);

        return redirect()->route('arena.show', $conversation);
    }

    /** Convert a genuine NAi reply into a tracked project task. */
    public function createTask(Request $request, Message $message)
    {
        $conversation = $message->conversation;
        abort_unless($conversation && $conversation->user_id === auth()->id(), 403);
        abort_unless($message->isActionable(), 422, 'Only a completed NAi reply can be converted into a task.');
        if ($message->task) return back()->with('error', 'That reply has already been saved as a task.');

        $data = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $project = Project::findOrFail($data['project_id']);
        $task = $project->tasks()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? Str::limit($message->content, 1900),
            'status' => 'backlog',
            'priority' => $data['priority'],
            'source_message_id' => $message->id,
        ]);

        Audit::log('task.created', 'Created task “'.$task->title.'” in '.$project->key.' from an NAi reply', $task);

        return back()->with('success', 'Task added to '.$project->key.' from the NAi reply.');
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
        Audit::log('arena.test', 'Tested the NAi connection: '.$result['status']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function viewData(ArenaGateway $arena): array
    {
        return [
            'conversation' => null,
            'conversations' => Conversation::where('user_id', auth()->id())->latest('last_message_at')->latest('id')->limit(30)->get(),
            'arenaStatus' => $arena->status(),
            'arenaConfigured' => $arena->isConfigured(),
            'subject' => null, 'subjectType' => null, 'subjectId' => null, 'playbooks' => [],
            'projects' => Project::orderBy('name')->limit(100)->get(['id', 'name', 'key']),
        ];
    }

    private function processPrompt(Conversation $conversation, string $prompt, ArenaGateway $arena): void
    {
        Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => $prompt, 'status' => 'complete']);
        try {
            $reply = $arena->chat($conversation, $prompt);
            Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => $reply['content'], 'status' => 'complete', 'arena_request_id' => $reply['request_id'], 'tokens' => $reply['tokens']]);
            $conversation->update(['last_message_at' => now(), 'arena_session_id' => $reply['session_id'] ?? $conversation->arena_session_id, 'title' => $conversation->title === 'New conversation' ? Str::limit($prompt, 60) : $conversation->title]);
            Audit::log('arena.chat', 'Sent a prompt to NAi', $conversation);
        } catch (\Throwable $e) {
            Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'NAi did not return a response.', 'status' => 'failed', 'error' => $e->getMessage()]);
            $conversation->update(['last_message_at' => now()]);
            Audit::log('arena.error', 'NAi request failed: '.$e->getMessage(), $conversation);
        }
    }
}
