@props(['caption' => null])
<div class="md-table-wrap">
    @if($caption)
        <caption class="sr-only">{{ $caption }}</caption>
    @endif
    <div class="table-scroll-wrap">
        <table role="grid">
            {{ $slot }}
        </table>
    </div>
</div>
