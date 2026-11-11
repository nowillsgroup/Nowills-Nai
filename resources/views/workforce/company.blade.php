<x-layout title="AI Workforce" :subtitle="$company->name">
<div class="page-head"><div><h2>{{ $company->name }}</h2><p>Company-scoped assignments, approvals and work items.</p></div><a class="btn" href="{{ route('workforce.index') }}">Switch company</a></div>
<div class="notice warn"><span>Only the Operations work-item tool is available. It creates a tracked record here, not in an ERP or external system. Prompts are sent to OpenAI and may incur charges. Do not include secrets.</span></div>
<div class="workforce-stats" style="margin:20px 0">
@foreach(['queued'=>'Queued','planning'=>'Planning','awaiting_approval'=>'Awaiting approval','completed'=>'Completed','failed'=>'Failed','unsupported'=>'Unsupported'] as $key => $label)
<div class="card"><span class="muted">{{ $label }}</span><h2>{{ $counts[$key] ?? 0 }}</h2></div>
@endforeach
</div>
<div class="settings-grid">
<div class="grid" style="gap:18px">
<div class="card"><div class="card-head"><h3>Assign work</h3></div>
@if($canOperate)
<form method="POST" action="{{ route('workforce.submit',$company) }}">@csrf<div class="field"><label for="instruction">Describe an internal work item to create</label><textarea class="input" id="instruction" name="instruction" maxlength="4000" required rows="4" placeholder="Create a work item to prepare the next operations meeting."></textarea></div><button class="btn btn-primary">Queue assignment</button></form>
@else<p class="muted">You have read-only access.</p>@endif
<p class="faint" style="margin-top:12px">Planning requires a configured server-side API key and a running workforce queue worker. A manager must review every proposed operation.</p></div>
<div class="card"><div class="card-head"><h3>Assignments and approvals</h3></div>
@forelse($assignments as $a)
<div style="padding:14px 0;border-top:1px solid var(--border)"><div class="flex justify-between"><strong>Assignment #{{ $a->id }}</strong><span class="badge">{{ str_replace('_',' ', $a->status) }}</span></div><p style="white-space:pre-wrap">{{ $a->instruction }}</p>
@if($a->proposal)<p><strong>Proposed by Operations:</strong> Create work item<br>Title: {{ $a->proposal['title'] ?? '' }}<br>Details: {{ $a->proposal['description'] ?? '' }}</p>@endif
@if($a->result)<p>Created work item #{{ $a->result['work_item_id'] ?? '' }}.</p>@endif
@if($a->status === 'unsupported')<p>No available agent can execute this request. No operation was performed.</p>@endif
@if($a->error)<p class="muted">{{ $a->error }}</p>@endif
@if($canOperate && in_array($a->status, ['failed', 'unsupported'], true))<form method="POST" action="{{ route('workforce.retry',[$company,$a->id]) }}">@csrf<button class="btn btn-sm">Retry planning (may incur charges)</button></form>@endif
@if($a->status === 'awaiting_approval' && $canManage)<form method="POST" action="{{ route('workforce.decide',[$company,$a->id]) }}" class="flex gap-8">@csrf<button class="btn btn-primary" name="decision" value="approve">Approve and create</button><button class="btn" name="decision" value="reject">Reject</button></form>@endif
</div>
@empty <p class="muted">No assignments yet.</p> @endforelse</div>
<div class="card"><div class="card-head"><h3>Work items</h3></div>
@forelse($items as $item)<div style="padding:12px 0;border-top:1px solid var(--border)"><strong>#{{ $item->id }} · {{ $item->title }}</strong><div class="muted" style="white-space:pre-wrap">{{ $item->description }}</div><span class="faint">{{ $item->status }} · Assignment #{{ $item->assignment_id }}</span>@if($canOperate && $item->status === 'open')<form method="POST" action="{{ route('workforce.items.complete',[$company,$item->id]) }}" style="margin-top:8px">@csrf<button class="btn btn-sm">Mark complete</button></form>@endif</div>@empty<p class="muted">No work items have been created.</p>@endforelse</div>
</div>
<div class="grid" style="gap:18px;align-content:start">
<div class="card"><div class="card-head"><h3>Agent directory</h3></div><p><strong>Operations</strong> · Work-item creation available with approval</p><p class="muted">Business Development, Finance, Customer Service, Technical Support, Sales, Marketing, HR, Procurement, Executive Assistant, Legal &amp; Compliance and Data Analyst: not yet implemented.</p></div>
<div class="card"><div class="card-head"><h3>Usage</h3></div><p>Input tokens: {{ $tokenUsage->input_total ?? 0 }}<br>Output tokens: {{ $tokenUsage->output_total ?? 0 }}</p><p class="faint">Provider-reported usage for recorded plans only. Costs are not yet calculated; check your provider billing.</p></div>
<div class="card"><div class="card-head"><h3>Activity</h3></div>@forelse($events as $event)<p class="muted">{{ $event->created_at?->format('Y-m-d H:i') }} · {{ str_replace('_',' ', $event->event) }} @if($event->assignment_id)· #{{ $event->assignment_id }}@endif · user {{ $event->actor_id ?? 'unavailable' }}</p>@empty<p class="muted">No activity yet.</p>@endforelse</div>
@if(auth()->user()->isAdmin() && $canManage)<div class="card"><div class="card-head"><h3>Add company member</h3></div><form method="POST" action="{{ route('workforce.members',$company) }}">@csrf<div class="field"><label>User</label><select class="input" name="user_id" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></div><div class="field"><label>Company role</label><select class="input" name="role"><option value="viewer">Viewer</option><option value="operator">Operator</option><option value="manager">Manager</option></select></div><button class="btn">Add member</button></form><p class="faint" style="margin-top:10px">Global viewer accounts remain read-only even when given an operator or manager company role.</p>
@foreach($members as $member)<div style="padding:8px 0;border-top:1px solid var(--border)"><span>{{ $users->firstWhere('id', $member->user_id)?->name ?? 'Unknown user' }} · {{ $member->role }}</span><form method="POST" action="{{ route('workforce.members.remove',[$company,$member->id]) }}">@csrf @method('DELETE')<button class="btn btn-sm">Remove membership</button></form></div>@endforeach</div>@endif
</div>
</div>
</x-layout>
