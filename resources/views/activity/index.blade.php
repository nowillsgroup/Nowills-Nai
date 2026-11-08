<x-layout title="Activity" subtitle="Workspace audit trail">
<div class="page-head"><div><h2>Activity</h2><p>A chronological audit trail of changes, access events, and NAi interactions.</p></div><div class="head-actions"><a href="{{ route('activity.export') }}" class="btn"><x-icon name="download" :size="16" />Export CSV</a></div></div>
<form method="GET" class="toolbar"><select name="action" onchange="this.form.submit()"><option value="">All events</option>@foreach(['inventory'=>'Inventory','project'=>'Projects','task'=>'Tasks','arena'=>'NAi','auth'=>'Authentication','settings'=>'Settings','user'=>'Team'] as $val=>$label)<option value="{{ $val }}" @selected(request('action')===$val)>{{ $label }}</option>@endforeach</select></form>
<div class="card"><div class="activity-list">
@forelse($activities as $activity)
<div class="activity-item"><span class="activity-dot"><x-icon name="activity" :size="15" /></span><div class="activity-body"><p>{{ $activity->description }}</p><span>{{ $activity->user?->name ?? 'System' }} · {{ $activity->action }} · {{ $activity->created_at->format('M j, Y H:i') }} ({{ $activity->created_at->diffForHumans() }})</span></div></div>
@empty
<div class="empty"><x-icon name="activity" :size="30" /><h3>No activity</h3><p>Events will appear here as the workspace is used.</p></div>
@endforelse
</div></div>
<x-pagination :paginator="$activities" />
</x-layout>
