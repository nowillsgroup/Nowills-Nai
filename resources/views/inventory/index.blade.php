<x-layout :title="$cfg['label']" :subtitle="$cfg['description']">
<div class="page-head">
    <div><h2>{{ $cfg['label'] }}</h2><p>{{ $cfg['description'] }}</p></div>
    <div class="head-actions"><a href="{{ route('inventory.export',$type) }}" class="btn"><x-icon name="download" :size="16" />Export CSV</a>@can('write')<a href="{{ route('inventory.create',$type) }}" class="btn btn-primary"><x-icon name="plus" :size="16" />Add {{ strtolower($cfg['singular']) }}</a>@endcan</div>
</div>
<div class="grid grid-3" style="margin-bottom:18px">
    <div class="card stat-card"><div class="stat-label">Total {{ strtolower($cfg['label']) }}</div><div class="stat-value" style="margin-top:8px">{{ $counts['total'] }}</div></div>
    <div class="card stat-card"><div class="stat-label">Healthy / active</div><div class="stat-value" style="margin-top:8px;color:var(--green)">{{ $counts['active'] }}</div></div>
    <div class="card stat-card"><div class="stat-label">Needs review</div><div class="stat-value" style="margin-top:8px;color:var(--amber)">{{ max(0, $counts['total'] - $counts['active']) }}</div></div>
</div>
<form method="GET" class="toolbar">
    <div class="search-field"><input class="input" name="q" value="{{ request('q') }}" placeholder="Search {{ strtolower($cfg['label']) }}…"></div>
    <select name="status" onchange="this.form.submit()"><option value="">All statuses</option>@foreach($cfg['fields']['status']['options'] as $val=>$label)<option value="{{ $val }}" @selected(request('status')===$val)>{{ $label }}</option>@endforeach</select>
    @isset($cfg['fields']['environment'])<select name="environment" onchange="this.form.submit()"><option value="">All environments</option>@foreach($cfg['fields']['environment']['options'] as $val=>$label)<option value="{{ $val }}" @selected(request('environment')===$val)>{{ $label }}</option>@endforeach</select>@endisset
    <button class="btn" type="submit"><x-icon name="filter" :size="16" />Filter</button>
    @if(request()->hasAny(['q','status','environment']))<a href="{{ route('inventory.index',$type) }}" class="btn btn-ghost">Clear</a>@endif
</form>
<div class="table-wrap">
<table><thead><tr>@foreach($cfg['columns'] as $col)<th>{{ ucfirst(str_replace('_',' ',$col)) }}</th>@endforeach<th class="text-right">Actions</th></tr></thead><tbody>
@forelse($items as $item)
<tr>
@foreach($cfg['columns'] as $col)
<td>
@if($col==='name')<a href="{{ route('inventory.show',['type'=>$type,'id'=>$item->id]) }}" class="cell-title"><span class="cell-icon"><x-icon :name="$cfg['icon']" :size="17" /></span><span><strong>{{ $item->name }}</strong><span>{{ $item->description ?? '—' }}</span></span></a>
@elseif($col==='status')<x-badge :status="$item->status" />
@elseif($col==='environment')<x-badge :status="$item->environment" />
@elseif($col==='owner')<span class="muted">{{ $item->owner?->name ?? 'Unassigned' }}</span>
@elseif($col==='url')@if($item->url)<a href="{{ $item->url }}" target="_blank" rel="noopener" class="link faint"><x-icon name="external" :size="14" /> {{ parse_url($item->url, PHP_URL_HOST) }}</a>@else<span class="faint">—</span>@endif
@elseif($col==='provider')<span class="muted">{{ ucfirst(str_replace('_',' ',$item->provider)) }}</span>
@elseif($col==='branch')<span class="mono">{{ $item->default_branch }}</span>
@elseif($col==='host')<span class="mono">{{ $item->host }}{{ $item->port ? ':'.$item->port : '' }}</span>
@elseif($col==='engine')<span class="muted">{{ strtoupper($item->engine) }} {{ $item->version }}</span>
@elseif($col==='language')<span class="muted">{{ $item->language ?? '—' }}</span>
@elseif($col==='region')<span class="muted">{{ $item->region ?? '—' }}</span>
@else<span class="muted">{{ $item->$col ?? '—' }}</span>@endif
</td>
@endforeach
<td><div class="actions-cell">@can('write')<a class="icon-btn" href="{{ route('inventory.edit',['type'=>$type,'id'=>$item->id]) }}" title="Edit"><x-icon name="edit" :size="15" /></a><form method="POST" action="{{ route('inventory.destroy',['type'=>$type,'id'=>$item->id]) }}" data-confirm="Remove this {{ strtolower($cfg['singular']) }}?">@csrf @method('DELETE')<button class="icon-btn" title="Delete"><x-icon name="trash" :size="15" /></button></form>@endcan</div></td>
</tr>
@empty
<tr><td colspan="{{ count($cfg['columns'])+1 }}"><div class="empty"><x-icon :name="$cfg['icon']" :size="30" /><h3>No {{ strtolower($cfg['label']) }} found</h3><p>Register your first {{ strtolower($cfg['singular']) }} to bring it into the NAi workspace.</p>@can('write')<a href="{{ route('inventory.create',$type) }}" class="btn btn-primary"><x-icon name="plus" :size="16" />Add {{ strtolower($cfg['singular']) }}</a>@endcan</div></td></tr>
@endforelse
</tbody></table>
</div>
<x-pagination :paginator="$items" />
</x-layout>
