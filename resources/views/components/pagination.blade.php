@props(['paginator'])
@if($paginator->hasPages())
<nav class="pagination" aria-label="Pagination">
    @foreach($paginator->linkCollection() as $link)
        @if($link['url'] === null)
            <span class="disabled">{!! $link['label'] !!}</span>
        @elseif($link['active'])
            <span class="active"><span>{{ $link['label'] }}</span></span>
        @else
            <a href="{{ $link['url'] }}">{!! $link['label'] !!}</a>
        @endif
    @endforeach
</nav>
@endif
