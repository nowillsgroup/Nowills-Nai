@props(['points' => [], 'height' => 22])
@php
$bars = collect($points)->values();
$count = $bars->count();
$width = max(1, $count) * 6;
$colors = ['healthy' => '#34d399', 'degraded' => '#fbbf24', 'offline' => '#f87171'];
$up = $bars->where('status', 'healthy')->count();
@endphp
@if($count)
<svg class="sparkline" width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $up }} of {{ $count }} recent checks healthy">
    @foreach($bars as $i => $point)
    @php($tone = $colors[$point['status']] ?? '#64748b')
    <rect x="{{ $i * 6 }}" y="{{ $point['status'] === 'healthy' ? 4 : ($point['status'] === 'degraded' ? 8 : 12) }}" width="4" height="{{ $point['status'] === 'healthy' ? $height - 8 : ($point['status'] === 'degraded' ? $height - 12 : $height - 16) }}" rx="1.5" fill="{{ $tone }}"><title>{{ ucfirst($point['status']) }} · {{ \Illuminate\Support\Carbon::parse($point['at'])->format('M j H:i') }}@if(($point['latency'] ?? null) !== null) · {{ $point['latency'] }}ms @endif</title></rect>
    @endforeach
</svg>
@else
<span class="faint" style="font-size:11px">No history</span>
@endif
