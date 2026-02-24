@props(['items' => []])
@if(count($items) > 0)
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="{{ route('dashboard') }}" class="breadcrumb-item" aria-label="Home">
        <x-icon name="chart-bar" class="w-3.5 h-3.5" />
    </a>
    @foreach($items as $label => $url)
        <x-icon name="chevron-right" class="breadcrumb-sep w-3.5 h-3.5" />
        @if(!$loop->last && $url)
            <a href="{{ $url }}" class="breadcrumb-item">{{ $label }}</a>
        @else
            <span class="breadcrumb-current">{{ is_string($label) ? $label : $url }}</span>
        @endif
    @endforeach
</nav>
@endif
