@props(['title', 'description' => null, 'open' => null])
@php
    $expanded = $open ?? ($errors->any() && !old('_editing') && !old('id'));
@endphp
<details {{ $attributes->class('create-panel mb-6') }} data-create-panel {{ $expanded ? 'open' : '' }}>
    <summary class="create-panel-summary">
        <x-icon name="plus" class="w-4 h-4 shrink-0" />
        <span class="create-panel-title">{{ $title }}</span>
        @if($description)<span class="create-panel-description">{{ $description }}</span>@endif
        <x-icon name="chevron-down" class="create-panel-chevron w-4 h-4 shrink-0" />
    </summary>
    <div class="create-panel-body">{{ $slot }}</div>
</details>
