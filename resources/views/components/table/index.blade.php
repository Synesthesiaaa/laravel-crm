@props(['caption' => null])
<div {{ $attributes->class('md-table-wrap') }}>
    <div class="table-scroll-wrap" role="region" tabindex="0" aria-label="{{ $caption ?: 'Data table' }}">
        <table>
            @if($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif
            {{ $slot }}
        </table>
    </div>
    @isset($footer)
        {{ $footer }}
    @endisset
</div>
