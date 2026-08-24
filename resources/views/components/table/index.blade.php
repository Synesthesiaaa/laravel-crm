@props(['caption' => null])
<div {{ $attributes->class('md-table-wrap') }}>
    <div class="table-scroll-wrap">
        <table role="grid">
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
