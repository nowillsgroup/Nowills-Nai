<x-layout title="Monitoring" subtitle="Endpoint health, uptime, and incidents">
<div class="page-head">
    <div><h2>Monitoring</h2><p>NAi probes the endpoints registered in your inventory, keeps a check history, and opens an incident whenever a resource degrades or goes offline.</p></div>
    <div class="head-actions">
        <a href="{{ route('monitoring.export') }}" class="btn"><x-icon name="download" :size="16" />Export checks</a>
        @can('write')<form method="POST" action="{{ route('monitoring.run') }}">@csrf<button class="btn btn-primary"><x-icon name="refresh" :size="16" />Run checks now</button></form>@endcan
    </div>
</div>

@if($summary['demo_history'])
<div class="notice"><x-icon name="alert" :size="16" /><div><strong>Sample history.</strong> This workspace contains seeded demo checks (marked <code>source: demo</code>) so the board is explorable. Live probes are recorded as <code>source: probe</code>.</div></div>
@endif

@if($summary['checks'] === 0)
<div class="notice"><x-icon name="clock" :size="16" /><div><strong>No checks recorded yet.</strong> Run checks manually, or set <code>NAI_MONITORING_ENABLED=true</code> and enable the Laravel scheduler to probe every five minutes. Uptime figures stay blank until real checks exist — NAi does not invent measurements.</div></div>
@endif

<div class="grid grid-4" style="margin-bottom:18px">
    <div class="card stat-card"><div class="stat-top"><div class="stat-icon"><x-icon name="layers" /></div><span class="stat-trend"><x-icon name="clock" :size="12" />{{ $summary['checks'] }} checks / 24h</span></div><div class="stat-value">{{ $summary['monitored'] }}</div><div class="stat-label">Monitored resources</div></div>
    <div class="card stat-card"><div class="stat-top"><div class="stat-icon green"><x-icon name="check" /></div></div><div class="stat-value">@if($summary['uptime'] === null)<span class="faint">—</span>@else{{ $summary['uptime'] }}<span style="font-size:18px">%</span>@endif</div><div class="stat-label">Uptime across checks (24h)</div></div>
    <div class="card stat-card"><div class="stat-top"><div class="stat-icon pink"><x-icon name="alert" /></div>@if($summary['critical'])<span class="stat-trend" style="color:var(--red)">{{ $summary['critical'] }} critical</span>@endif</div><div class="stat-value">{{ $summary['open_incidents'] }}</div><div class="stat-label">Open incidents · {{ $summary['resolved_24h'] }} resolved today</div></div>
    <div class="card stat-card"><div class="stat-top"><div class="stat-icon purple"><x-icon name="bolt" /></div></div><div class="stat-value">@if($summary['latency'] === null)<span class="faint">—</span>@else{{ $summary['latency'] }}<span style="font-size:18px">ms</span>@endif</div><div class="stat-label">Average probe latency (24h)</div></div>
</div>

<div class="monitor-meta">
    <span><x-icon name="clock" :size="14" />Last live probe: {{ $summary['last_run'] ? \Illuminate\Support\Carbon::parse($summary['last_run'])->diffForHumans() : 'never' }}</span>
    <span><x-icon name="refresh" :size="14" />Scheduler: {{ $summary['scheduled'] ? 'enabled · every 5 minutes' : 'disabled' }}</span>
    <span><x-icon name="shield" :size="14" />History retained {{ $summary['retention_days'] }} days</span>
</div>

<form method="GET" class="toolbar">
    <select name="type" onchange="this.form.submit()"><option value="">All resource types</option>@foreach(\App\Support\Resources::labels() as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach</select>
    <select name="state" onchange="this.form.submit()"><option value="">All states</option><option value="attention" @selected($state === 'attention')>Needs attention</option><option value="healthy" @selected($state === 'healthy')>Healthy</option><option value="unchecked" @selected($state === 'unchecked')>Not yet checked</option></select>
    @if($type || $state)<a href="{{ route('monitoring.index') }}" class="btn btn-ghost">Clear</a>@endif
</form>

<div class="card" style="padding:0;overflow:hidden">
    <div class="card-head" style="padding:18px 20px 0"><h3>Resource health board</h3><span class="muted" style="font-size:12px">{{ count($rows) }} resources</span></div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table><thead><tr><th>Resource</th><th>Type</th><th>Status</th><th>Uptime 24h</th><th>Latency</th><th>Last 12 hours</th><th>Last check</th></tr></thead><tbody>
        @forelse($rows as $row)
            <tr>
                <td><a href="{{ route('inventory.show',['type'=>$row['type'],'id'=>$row['id']]) }}" class="cell-title"><span class="cell-icon"><x-icon :name="$row['icon']" :size="17" /></span><span><strong>{{ $row['name'] }}</strong><span>{{ $row['target'] ?? 'No probe target configured' }}</span></span></a></td>
                <td class="muted">{{ $row['label'] }}<div class="faint" style="font-size:11px">{{ ucfirst($row['environment']) }}</div></td>
                <td><x-badge :status="$row['status']" />@if(!$row['probeable'])<div class="faint" style="font-size:10.5px;margin-top:4px">Not probeable</div>@endif</td>
                <td style="min-width:110px">@if($row['uptime'] === null)<span class="faint">—</span>@else<div class="uptime"><strong>{{ $row['uptime'] }}%</strong><div class="progress"><div class="progress-bar {{ $row['uptime'] < 90 ? 'bar-red' : ($row['uptime'] < 99 ? 'bar-amber' : 'bar-green') }}" style="width:{{ max(2, $row['uptime']) }}%"></div></div><span class="faint">{{ $row['checks'] }} checks</span></div>@endif</td>
                <td>@if($row['latency'] === null)<span class="faint">—</span>@else{{ $row['latency'] }} ms @endif</td>
                <td><x-sparkline :points="$row['spark']" /></td>
                <td class="muted">@if($row['latest']){{ $row['latest']->checked_at->diffForHumans() }}@if($row['latest']->source === 'demo')<div class="faint" style="font-size:10.5px">demo sample</div>@endif @else<span class="faint">Never</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><x-icon name="activity" :size="30" /><h3>Nothing to monitor yet</h3><p>Register an application URL, database host, or infrastructure hostname, then run checks.</p><a href="{{ route('inventory.index','applications') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />Open inventory</a></div></td></tr>
        @endforelse
        </tbody></table>
    </div>
</div>

<div class="card section-gap">
    <div class="card-head"><h3>Open incidents</h3><span class="muted" style="font-size:12px">Auto-resolved when a resource recovers</span></div>
    @forelse($openIncidents as $incident)
    <div class="incident">
        <span class="incident-marker {{ $incident->severity }}"><x-icon name="alert" :size="16" /></span>
        <div class="incident-body">
            <div class="incident-head">
                <strong>{{ $incident->title }}</strong>
                <x-badge :status="$incident->severity" />
                @if($incident->source === 'demo')<span class="demo-chip">Sample</span>@endif
                <span class="faint">{{ $incident->durationForHumans() }} ongoing · {{ $incident->check_count }} failed check{{ $incident->check_count === 1 ? '' : 's' }}</span>
            </div>
            <p>{{ $incident->detail ?? 'No probe detail recorded.' }}</p>
            <span class="faint">{{ \App\Support\Resources::label($incident->resource_type) }} · started {{ $incident->started_at->format('M j, Y H:i') }}@if($incident->resourceUrl()) · <a class="link" href="{{ $incident->resourceUrl() }}">View resource</a>@endif</span>
            @can('write')
            <form method="POST" action="{{ route('monitoring.resolve',$incident) }}" class="incident-resolve">
                @csrf
                <input type="text" name="note" maxlength="1000" placeholder="Resolution note (optional)" class="input">
                <button class="btn"><x-icon name="check" :size="16" />Mark resolved</button>
            </form>
            @endcan
        </div>
    </div>
    @empty
    <div class="empty"><x-icon name="shield" :size="30" /><h3>No open incidents</h3><p>Every monitored resource is either healthy or has not been checked yet.</p></div>
    @endforelse
</div>

@if($resolvedIncidents->total())
<div class="card section-gap" style="padding:0;overflow:hidden">
    <div class="card-head" style="padding:18px 20px 0"><h3>Incident history</h3><span class="muted" style="font-size:12px">{{ $resolvedIncidents->total() }} resolved</span></div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table><thead><tr><th>Incident</th><th>Severity</th><th>Duration</th><th>Resolved</th><th>Resolution</th></tr></thead><tbody>
        @foreach($resolvedIncidents as $incident)
            <tr>
                <td><strong>{{ $incident->resource_name }}</strong><div class="faint" style="font-size:11px">{{ $incident->started_at->format('M j, Y H:i') }} · {{ \App\Support\Resources::label($incident->resource_type) }}</div></td>
                <td><x-badge :status="$incident->severity" /></td>
                <td class="muted">{{ $incident->durationForHumans() }}</td>
                <td class="muted">{{ $incident->resolved_at?->diffForHumans() }}</td>
                <td class="muted">@if($incident->resolution === 'manual')Manual · {{ $incident->resolver?->name ?? 'unknown' }}@else Auto-recovered @endif @if($incident->resolution_note)<div class="faint" style="font-size:11px">{{ $incident->resolution_note }}</div>@endif</td>
            </tr>
        @endforeach
        </tbody></table>
    </div>
</div>
<x-pagination :paginator="$resolvedIncidents" />
@endif
</x-layout>
