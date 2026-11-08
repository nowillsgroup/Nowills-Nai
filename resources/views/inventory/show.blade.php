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
<a href="{{ route('arena.index') }}" class="btn btn-block mt-16"><x-icon name="sparkles" :size="16" />Ask Arena AI about this resource</a>
</div>
</div>
</x-layout>
