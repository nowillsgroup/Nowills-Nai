<x-layout title="NAi" subtitle="AI development engine workspace">
<div class="arena-layout">
<div class="arena-sidebar">
<form method="POST" action="{{ route('arena.store') }}">@csrf<button class="btn btn-primary btn-block"><x-icon name="plus" :size="16" />New conversation</button></form>
<div class="card" style="padding:12px;flex:1"><div class="nav-label" style="padding-top:4px">Conversations</div><div class="conversation-list">
@forelse($conversations as $conv)
<a href="{{ route('arena.show',$conv) }}" class="conversation-item {{ isset($conversation) && $conversation->id===$conv->id ? 'active':'' }}"><strong>{{ $conv->title }}</strong>@if($conv->isScoped())<span class="scope-line"><x-icon :name="\App\Support\Subjects::icon($conv->subject_type)" :size="12" />{{ $conv->scopeLabel() }}</span>@endif<span>{{ $conv->last_message_at?->diffForHumans() ?? $conv->created_at->diffForHumans() }}</span></a>
@empty
<div class="faint" style="font-size:12px;padding:10px">No conversations yet.</div>
@endforelse
</div></div>
<div class="card" style="padding:14px"><div class="flex items-center justify-between"><strong style="font-size:12px">Engine status</strong><x-badge :status="$arenaStatus" /></div><p class="faint" style="font-size:11.5px;margin:10px 0 0">NAi sends prompts and workspace context to your configured AI provider. No local model or simulated responses.</p>@can('write')<form method="POST" action="{{ route('arena.test') }}" class="mt-16">@csrf<button class="btn btn-sm btn-block"><x-icon name="refresh" :size="14" />Test connection</button></form>@endcan</div>
</div>
<div class="arena-main">
@if($subject)
<div class="scope-banner">
    <span class="cell-icon"><x-icon :name="\App\Support\Subjects::icon($subjectType)" :size="17" /></span>
    <div><strong>{{ $subject->name }}</strong><span>Scoped context: {{ \App\Support\Subjects::label($subjectType) }} metadata @if(in_array($subjectType, \App\Support\Resources::MONITORED, true)), recent checks, and open incidents @endif</span></div>
    @if(\App\Support\Subjects::url($subjectType, $subject->id))<a href="{{ \App\Support\Subjects::url($subjectType, $subject->id) }}" class="btn btn-sm">View record <x-icon name="arrow-right" :size="14" /></a>@endif
</div>
@endif
@if(!$conversation)
<div class="arena-empty">
<div class="arena-logo"><x-icon name="sparkles" :size="34" /></div>
<h2>@if($subject)Ask NAi about <span class="gradient-text">{{ $subject->name }}</span>@else Build with <span class="gradient-text">NAi</span>@endif</h2>
<p>@if($subject)Pick a playbook or write your own prompt. NAi sends this record's operational context to your configured AI provider.@else Ask NAi to write, explain and debug code or plan your development. Your configured AI provider generates the answers. NAi cannot access or edit your repository automatically.@endif</p>
@if(!$arenaConfigured)<div class="notice warn" style="max-width:560px"><x-icon name="alert" :size="16" /><span>NAi is not connected. @can('admin')<a href="{{ route('settings.edit') }}" style="text-decoration:underline">Configure an AI provider</a>@else Ask an administrator to configure an AI provider.@endcan</span></div>@endif
@if($subject && count($playbooks))
<div class="playbook-grid">
    @foreach($playbooks as $key => $playbook)
    <form method="POST" action="{{ route('arena.store') }}">@csrf
        <input type="hidden" name="subject_type" value="{{ $subjectType }}"><input type="hidden" name="subject_id" value="{{ $subject->id }}"><input type="hidden" name="playbook" value="{{ $key }}">
        <button class="playbook-card" @disabled(!$arenaConfigured)><strong><x-icon name="sparkles" :size="14" />{{ $playbook['label'] }}</strong><span>{{ $playbook['description'] }}</span></button>
    </form>
    @endforeach
</div>
@elseif(!$subject)
<div class="notice" style="max-width:560px"><span>Prompts, recent messages and workspace metadata are sent to the configured provider. Avoid sharing secrets.</span></div>
<div class="prompt-grid">
<button class="prompt-chip" data-prompt="Help me build a Laravel feature. Start by asking what it should do, then provide the code and tests."><strong>Build a feature</strong>Plan and write Laravel code</button>
<button class="prompt-chip" data-prompt="Help me debug this code. Ask me for the error and relevant code first."><strong>Debug my code</strong>Find the cause and suggest a fix</button>
<button class="prompt-chip" data-prompt="Audit our database inventory and recommend a backup and resilience checklist."><strong>Database resilience</strong>Backup posture and recovery readiness</button>
<button class="prompt-chip" data-prompt="Propose an architecture review checklist for our Laravel and MySQL applications."><strong>Architecture review</strong>Laravel + MySQL best-practice checklist</button>
</div>
@endif
<form method="POST" action="{{ route('arena.store') }}" style="width:100%;max-width:560px">@csrf
    @if($subject)<input type="hidden" name="subject_type" value="{{ $subjectType }}"><input type="hidden" name="subject_id" value="{{ $subject->id }}">@endif
    <textarea name="prompt" id="arenaFirstPrompt" class="input" placeholder="{{ $subject ? 'Ask NAi about '.$subject->name.'…' : 'Ask NAi to write, explain or debug code…' }}" style="min-height:80px;margin-bottom:10px"></textarea>
    <button class="btn btn-primary btn-block" @disabled(!$arenaConfigured)><x-icon name="send" :size="16" />Start conversation</button>
</form>
</div>
@else
<div class="arena-header"><div><h3>{{ $conversation->title }}</h3><span class="faint" style="font-size:11.5px">{{ $conversation->messages->count() }} messages · NAi engine @if($conversation->playbookLabel()) · {{ $conversation->playbookLabel() }} playbook @endif</span></div><div class="flex gap-8 items-center"><x-badge :status="$arenaStatus" />@if($conversation->isScoped())<a href="{{ route('arena.start',['type'=>$conversation->subject_type,'id'=>$conversation->subject_id]) }}" class="btn btn-sm"><x-icon name="plus" :size="14" />New scoped chat</a>@endif @can('write')<form method="POST" action="{{ route('arena.destroy',$conversation) }}" data-confirm="Delete this conversation?">@csrf @method('DELETE')<button class="icon-btn"><x-icon name="trash" :size="15" /></button></form>@endcan</div></div>
<div class="arena-messages">
@forelse($conversation->messages as $message)
<div class="message {{ $message->role }} {{ $message->status==='failed'?'failed':'' }}"><div class="message-avatar">@if($message->role==='assistant')<x-icon name="sparkles" :size="15" />@else{{ auth()->user()->initials() }}@endif</div><div style="min-width:0"><div class="message-bubble">{{ $message->content }}</div>@if($message->error)<div class="message-error">{{ $message->error }}</div>@endif<div class="faint" style="font-size:10.5px;margin-top:5px">{{ $message->role==='assistant'?'NAi':'You' }} · {{ $message->created_at->format('H:i') }}@if($message->tokens) · {{ $message->tokens }} tokens @endif</div>
    @if($message->isActionable())
        @if($message->task)
        <a href="{{ route('projects.show',$message->task->project_id) }}" class="task-link"><x-icon name="check" :size="13" />Saved as task in {{ $message->task->project?->key ?? 'project' }}: {{ \Illuminate\Support\Str::limit($message->task->title,60) }}</a>
        @else
        @can('write')
        <details class="save-task">
            <summary><x-icon name="plus" :size="13" />Save reply as a task</summary>
            @if($projects->count())
            <form method="POST" action="{{ route('arena.task',$message) }}" class="save-task-form">@csrf
                <input type="text" name="title" class="input" maxlength="200" required value="{{ \Illuminate\Support\Str::limit(explode("\n", trim($message->content))[0], 150, '') }}" placeholder="Task title">
                <div class="flex gap-8" style="flex-wrap:wrap">
                    <select name="project_id" required>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->key }} · {{ $project->name }}</option>@endforeach</select>
                    <select name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select>
                    <button class="btn btn-sm btn-primary"><x-icon name="check" :size="14" />Create task</button>
                </div>
                <span class="faint" style="font-size:11px">The reply is attached as the task description and linked back to this conversation.</span>
            </form>
            @else
            <p class="faint" style="font-size:11.5px;margin:8px 0 0">Create a project first to track work from NAi replies.</p>
            @endif
        </details>
        @endcan
        @endif
    @endif
</div></div>
@empty
<div class="empty"><p>Send a prompt to begin. Responses are generated by your configured AI provider.</p></div>
@endforelse
</div>
<div class="arena-composer">
@if(!$arenaConfigured)<div class="notice warn" style="margin-bottom:10px"><x-icon name="alert" :size="15" /><span>Connect NAi in Settings to send prompts. NAi will not fabricate responses.</span></div>@endif
<form method="POST" action="{{ route('arena.message',$conversation) }}">@csrf<textarea name="content" id="arenaPrompt" placeholder="Message NAi…" required @disabled(!$arenaConfigured)></textarea><button class="btn btn-primary" @disabled(!$arenaConfigured)><x-icon name="send" :size="16" />Send</button></form>
</div>
@endif
</div>
</div>
</x-layout>
