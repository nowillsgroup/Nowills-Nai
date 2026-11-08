@props(['status', 'label' => null])
@php
$s = strtolower((string) $status);
$tone = match(true) {
 in_array($s, ['healthy','online','active','current','connected','done','completed','low']) => 'green',
 in_array($s, ['degraded','due','syncing','maintenance','error','medium','review','in_progress']) => 'amber',
 in_array($s, ['offline','failed','archived','urgent','high']) => 'red',
 in_array($s, ['production']) => 'blue',
 in_array($s, ['staging']) => 'purple',
 in_array($s, ['development','planning','backlog']) => 'slate',
 default => 'slate',
};
$text = $label ?? ucfirst(str_replace('_', ' ', $s));
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-'.$tone]) }}><span class="badge-dot"></span>{{ $text }}</span>
