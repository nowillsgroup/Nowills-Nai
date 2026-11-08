<x-layout :title="$item->name" :subtitle="$cfg['singular']">
<div class="page-head"><div><h2>{{ $item->name }}</h2><p>{{ $item->description ?? $cfg['description'] }}</p></div><div class="head-actions">@can('write')<a href="{{ route('inventory.edit',['type'=>$type,'id'=>$item->id]) }}" class="btn"><x-icon name="edit" :size="16" />Edit</a><form method="POST" action="{{ route('inventory.destroy',['type'=>$type,'id'=>$item->id]) }}" data-confirm="Remove this {{ strtolower($cfg['singular']) }}?">@csrf @method('DELETE')<button class="btn btn-danger"><x-icon name="trash" :size="16" />Delete</button></form>@endcan</div></div>
<div class="grid grid-2">
<div class="card"><div class="card-head"><h3>Details</h3><x-badge :status="$item->status" /></div><dl>
@foreach($cfg['fields'] as $name=>$field)
@php $value=$item->$name; if($name==='tech_stack' && is_array($value)) $value=implode(', ',$value); if($field['type']==='owner') $value=$item->owner?->name; if($field['type']==='application') $value=$item->application?->name; @endphp
<div class="kv"><dt>{{ $field['label'] }}</dt><dd>@if($name==='url' && $value)<a href="{{ $value }}" target="_blank" rel="noopener" class="link" style="color:var(--blue)">{{ $value }} <x-icon name="external" :size="13" /></a>@else{{ $value ?: '—' }}@endif</dd></div>
@endforeach
<div class="kv"><dt>Registered</dt><dd>{{ $item->created_at->format('M j, Y H:i') }}</dd></div>
</dl></div>
<div class="card"><div class="card-head"><h3>Operational context</h3></div>
@if($type==='applications')
<div class="kv"><dt>Repository</dt><dd>{{ $item->repository?->name ?? 'Not linked' }}</dd></div>
<div class="kv"><dt>Databases</dt><dd>{{ $item->databases->count() }}</dd></div>
<div class="kv"><dt>Infrastructure</dt><dd>{{ $item->infrastructure->count() }}</dd></div>
<div class="kv"><dt>Last checked</dt><dd>{{ $item->last_checked_at?->diffForHumans() ?? 'Never' }}</dd></div>
@endif
<div class="notice mt-16"><x-icon name="shield" :size="16" /><span>NAi stores operational metadata only. Credentials and secrets belong in your approved secret manager, not in inventory notes.</span></div>
<a href="{{ route('arena.start',['type'=>$type,'id'=>$item->id]) }}" class="btn btn-primary btn-block mt-16"><x-icon name="sparkles" :size="16" />Ask Arena AI about this {{ strtolower($cfg['singular']) }}</a>
<p class="faint" style="font-size:11px;margin:8px 0 0;text-align:center">Opens a scoped conversation with playbooks. Arena AI generates all responses.</p>
</div>
</div>
@if(in_array($type, \App\Support\Resources::MONITORED, true))
<div class="card section-gap">
    <div class="card-head"><h3>Monitoring</h3><a class="link" href="{{ route('monitoring.index',['type'=>$type]) }}">Open monitoring <x-icon name="arrow-right" :size="14" /></a></div>
    @if($incidents->count())
        @foreach($incidents as $incident)
        <div class="incident" style="padding-top:0"><span class="incident-marker {{ $incident->severity }}"><x-icon name="alert" :size="16" /></span><div class="incident-body"><div class="incident-head"><strong>{{ $incident->title }}</strong><x-badge :status="$incident->severity" /><span class="faint">{{ $incident->durationForHumans() }} ongoing</span></div><p>{{ $incident->detail }}</p></div></div>
        @endforeach
    @endif
    @if($checks->count())
    <div class="flex items-center gap-12" style="flex-wrap:wrap;margin-bottom:12px"><x-sparkline :points="$checks->reverse()->values()->map(fn ($c) => ['status'=>$c->status,'at'=>$c->checked_at->toIso8601String(),'latency'=>$c->latency_ms])->all()" /><span class="faint" style="font-size:11.5px">Last {{ $checks->count() }} checks · most recent {{ $checks->first()->checked_at->diffForHumans() }}@if($checks->first()->source === 'demo') (demo sample)@endif</span></div>
    <div class="table-wrap"><table><thead><tr><th>Checked</th><th>Status</th><th>Latency</th><th>Detail</th></tr></thead><tbody>
    @foreach($checks->take(6) as $check)
        <tr><td class="muted">{{ $check->checked_at->format('M j, H:i') }}</td><td><x-badge :status="$check->status" /></td><td class="muted">@if($check->latency_ms === null)<span class="faint">—</span>@else{{ $check->latency_ms }} ms @endif</td><td class="muted">{{ $check->message ?? '—' }}</td></tr>
    @endforeach
    </tbody></table></div>
    @else
    <div class="empty" style="padding:28px 16px"><x-icon name="gauge" :size="26" /><h3>No checks recorded</h3><p>Run checks from the monitoring page to build a history for this resource.</p></div>
    @endif
</div>
@endif
</x-layout>
