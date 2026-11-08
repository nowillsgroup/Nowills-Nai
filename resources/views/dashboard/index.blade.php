<x-layout title="Dashboard" subtitle="Centralized operations overview">
@php
$first = explode(' ', auth()->user()->name)[0];
$gradientParts = []; $offset = 0; $totalMix = max(1, collect($resourceMix)->sum('value'));
foreach($resourceMix as $mix){ $end = $offset + ($mix['value'] / $totalMix * 100); $gradientParts[] = $mix['color'].' '.$offset.'% '.$end.'%'; $offset = $end; }
$donut = $offset ? 'conic-gradient('.implode(',', $gradientParts).')' : 'conic-gradient(#1a2238 0 100%)';
@endphp
<div class="hero-card">
    <div>
        <h2>Welcome back, <span class="gradient-text">{{ $first }}</span></h2>
        <p>Your entire technical estate in one command center. Monitor applications, coordinate projects, and orchestrate development through Arena AI.</p>
        <div class="flex gap-8 mt-16">
            <a href="{{ route('arena.index') }}" class="btn btn-primary"><x-icon name="sparkles" :size="16" />Open Arena AI</a>
            @can('write')<a href="{{ route('inventory.create','applications') }}" class="btn"><x-icon name="plus" :size="16" />Add application</a><form method="POST" action="{{ route('monitoring.run') }}">@csrf<button class="btn" title="Probe registered endpoints"><x-icon name="refresh" :size="16" />Run checks</button></form>@endcan
        </div>
    </div>
    <div class="hero-metrics">
        <div class="hero-metric"><strong>{{ $stats['total'] }}</strong><span>Resources</span></div>
        <div class="hero-metric"><strong>{{ $healthPercent }}%</strong><span>Healthy</span></div>
        <div class="hero-metric"><strong>{{ $attention }}</strong><span>Need attention</span></div>
    </div>
</div>

@if($openIncidentCount)
<div class="incident-banner">
    <span class="incident-marker {{ $openIncidents->first()->severity }}"><x-icon name="alert" :size="18" /></span>
    <div><strong>{{ $openIncidentCount }} open incident{{ $openIncidentCount === 1 ? '' : 's' }} need attention</strong><span>{{ $openIncidents->map(fn ($i) => $i->resource_name.' ('.$i->durationForHumans().')')->implode(' · ') }}</span></div>
    <a href="{{ route('monitoring.index') }}" class="btn"><x-icon name="gauge" :size="16" />Open monitoring</a>
</div>
@endif

<div class="grid grid-4" style="margin-bottom:18px">
    <div class="card stat-card"><div class="stat-top"><div class="stat-icon"><x-icon name="apps" /></div><span class="stat-trend"><x-icon name="activity" :size="12" />Live inventory</span></div><div class="stat-value">{{ $stats['applications'] }}</div><div class="stat-label">Applications</div></div>
    <div class="card stat-card"><div class="stat-top"><div class="stat-icon purple"><x-icon name="git" /></div></div><div class="stat-value">{{ $stats['repositories'] }}</div><div class="stat-label">Repositories</div></div>
    <div class="card stat-card"><div class="stat-top"><div class="stat-icon pink"><x-icon name="database" /></div></div><div class="stat-value">{{ $stats['databases'] }}</div><div class="stat-label">Databases</div></div>
    <div class="card stat-card"><div class="stat-top"><div class="stat-icon green"><x-icon name="server" /></div></div><div class="stat-value">{{ $stats['infrastructure'] }}</div><div class="stat-label">Infrastructure resources</div></div>
</div>

<div class="dashboard-grid">
    <div class="grid" style="gap:18px">
        <div class="card" style="padding:0;overflow:hidden">
            <div class="card-head" style="padding:18px 20px 0"><h3>Applications</h3><a class="link" href="{{ route('inventory.index','applications') }}">View all <x-icon name="arrow-right" :size="14" /></a></div>
            <div class="table-wrap" style="border:0;border-radius:0">
                <table><thead><tr><th>Application</th><th>Environment</th><th>Status</th><th>Owner</th><th class="text-right">Health</th></tr></thead><tbody>
                @forelse($apps as $app)
                    <tr>
                        <td><a href="{{ route('inventory.show',['type'=>'applications','id'=>$app->id]) }}" class="cell-title"><span class="cell-icon"><x-icon name="apps" :size="17" /></span><span><strong>{{ $app->name }}</strong><span>{{ $app->url ? parse_url($app->url, PHP_URL_HOST) : 'No URL configured' }}</span></span></a></td>
                        <td><x-badge :status="$app->environment" /></td>
                        <td><x-badge :status="$app->status" /></td>
                        <td class="muted">{{ $app->owner?->name ?? 'Unassigned' }}</td>
                        <td class="text-right">@if($app->health_score !== null)<strong>{{ $app->health_score }}%</strong>@else<span class="faint">—</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty"><x-icon name="apps" :size="28" /><h3>No applications yet</h3><p>Add your first application to start monitoring.</p></div></td></tr>
                @endforelse
                </tbody></table>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Active projects</h3><a class="link" href="{{ route('projects.index') }}">View all <x-icon name="arrow-right" :size="14" /></a></div>
            <div class="grid grid-2">
                @forelse($projects as $project)
                <a href="{{ route('projects.show',$project) }}" class="card project-card" style="background:var(--bg-2);padding:16px">
                    <div class="flex items-center justify-between"><span class="project-key">{{ $project->key }}</span><x-badge :status="$project->priority" /></div>
                    <h3>{{ $project->name }}</h3>
                    <p>{{ $project->description }}</p>
                    <div><div class="flex justify-between" style="font-size:11px;color:var(--faint);margin-bottom:6px"><span>{{ $project->progress() }}% complete</span><span>{{ $project->tasks_count }} tasks</span></div><div class="progress"><div class="progress-bar" style="width:{{ $project->progress() }}%"></div></div></div>
                    <div class="project-meta"><span>{{ $project->owner?->name ?? 'Unassigned' }}</span><span>@if($project->due_date)Due {{ $project->due_date->format('M j') }}@else No due date @endif</span></div>
                </a>
                @empty
                <div class="empty" style="grid-column:1/-1"><x-icon name="folder" :size="28" /><h3>No active projects</h3><p>Create a project to coordinate development work.</p></div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Infrastructure health</h3><a class="link" href="{{ route('inventory.index','infrastructure') }}">Manage <x-icon name="arrow-right" :size="14" /></a></div>
            @forelse($infra as $node)
            <div class="infra-row"><span class="cell-icon"><x-icon name="server" :size="16" /></span><div class="info"><strong>{{ $node->name }}</strong><span>{{ $node->provider }} · {{ $node->region ?? 'Global' }}</span></div><x-badge :status="$node->status" /></div>
            @empty
            <div class="empty"><p>No infrastructure resources registered.</p></div>
            @endforelse
        </div>
    </div>

    <div class="grid" style="gap:18px">
        <div class="card">
            <div class="card-head"><h3>Arena AI engine</h3><x-badge :status="$arenaStatus" /></div>
            <div class="flex gap-12 items-center" style="margin-bottom:14px"><div class="engine-icon" style="width:42px;height:42px"><x-icon name="sparkles" :size="22" /></div><div><strong style="font-size:13px">Orchestration layer</strong><div class="faint" style="font-size:11.5px">NAi connects to Arena AI for development intelligence.</div></div></div>
            @if(!$arenaConfigured)
            <div class="notice warn" style="margin-bottom:14px"><x-icon name="alert" :size="16" /><span>Arena AI is not connected yet. Configure the upstream endpoint to enable AI-assisted workflows.</span></div>
            @can('admin')<a href="{{ route('settings.edit') }}" class="btn btn-block"><x-icon name="settings" :size="16" />Connect Arena AI</a>@endcan
            @else
            <a href="{{ route('arena.index') }}" class="btn btn-primary btn-block"><x-icon name="sparkles" :size="16" />Open Arena workspace</a>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h3>Estate health</h3></div>
            <div class="health-ring">
                <div class="ring" style="background:conic-gradient(var(--green) {{ $healthPercent }}%, rgba(148,163,184,.12) 0)"><strong>{{ $healthPercent }}%</strong></div>
                <p>Monitored applications, databases, and infrastructure reporting healthy or online.</p>
            </div>
            @if($attention > 0)<div class="notice warn mt-16 mb-0"><x-icon name="alert" :size="16" /><span>{{ $attention }} item{{ $attention === 1 ? '' : 's' }} need attention.</span></div>@endif
        </div>

        <div class="card">
            <div class="card-head"><h3>Resource mix</h3></div>
            <div class="donut" style="background:{{ $donut }}"><div class="donut-center"><strong>{{ $stats['total'] }}</strong><span>total</span></div></div>
            <div class="legend">@foreach($resourceMix as $mix)<div class="legend-item"><span class="legend-dot" style="background:{{ $mix['color'] }}"></span>{{ $mix['label'] }}<strong>{{ $mix['value'] }}</strong></div>@endforeach</div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Recent activity</h3><a class="link" href="{{ route('activity.index') }}">All</a></div>
            <div class="activity-list">
                @forelse($activities as $activity)
                <div class="activity-item"><span class="activity-dot"><x-icon name="activity" :size="15" /></span><div class="activity-body"><p>{{ $activity->description }}</p><span>{{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at->diffForHumans() }}</span></div></div>
                @empty
                <div class="empty"><p>No activity recorded yet.</p></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
</x-layout>
